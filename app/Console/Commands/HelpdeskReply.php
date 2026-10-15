<?php

namespace App\Console\Commands;

use App\Ai\Agents\HelpdeskAgent;
use App\Enums\DemoMode;
use App\Models\Ticket;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Responses\AgentResponse;

use function Laravel\Prompts\confirm;

#[Signature('helpdesk:reply {ticket : The ticket id} {--secure : Use the authorized tools and require approval for refunds}')]
#[Description('Let the AI support assistant draft the reply to a support ticket')]
class HelpdeskReply extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $ticket = Ticket::with('user')->findOrFail($this->argument('ticket'));
        $mode = $this->option('secure') ? DemoMode::Secure : DemoMode::Vulnerable;

        $this->components->twoColumnDetail('Mode', $mode === DemoMode::Secure ? '<fg=green>SECURE</>' : '<fg=red>VULNERABLE</>');
        $this->components->twoColumnDetail('Ticket', "#{$ticket->id} — {$ticket->subject}");
        $this->components->twoColumnDetail('Customer', "{$ticket->user->name} (#{$ticket->user->id})");
        $this->newLine();
        $this->line($ticket->body);
        $this->newLine();

        $agent = new HelpdeskAgent($ticket->user, $mode);

        $response = $agent->forUser($ticket->user)->prompt(
            "Support ticket #{$ticket->id}\nSubject: {$ticket->subject}\n\n{$ticket->body}"
        );

        $this->printToolResults($response);

        while ($response->hasPendingApprovals()) {
            $decisions = $response->pendingApprovals->mapWithKeys(fn (PendingApproval $approval) => [
                $approval->id => $this->askForDecision($approval),
            ]);

            $response = $agent
                ->continue($response->conversationId, as: $ticket->user)
                ->prompt(Decisions::from($decisions->all()));

            $this->printToolResults($response);
        }

        $this->components->info('Draft reply');
        $this->line($response->text);

        return self::SUCCESS;
    }

    private function printToolResults(AgentResponse $response): void
    {
        foreach ($response->toolResults as $toolResult) {
            $this->line(sprintf('🔧 <fg=yellow>%s</>(%s)', $toolResult->name, json_encode($toolResult->arguments)));
            $this->line("   → {$toolResult->text()}");
        }
    }

    private function askForDecision(PendingApproval $approval): Decision
    {
        $this->line(sprintf('⏸️  <fg=cyan>%s</>(%s) is waiting for approval', $approval->tool, json_encode($approval->arguments)));

        return confirm($approval->reason ?? 'Approve this tool call?', default: false)
            ? Decision::approve()
            : Decision::reject('A human reviewer rejected this action.');
    }
}
