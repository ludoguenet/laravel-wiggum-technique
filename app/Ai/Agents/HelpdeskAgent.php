<?php

namespace App\Ai\Agents;

use App\Ai\Tools\LookupAnyInvoice;
use App\Ai\Tools\LookupInvoice;
use App\Ai\Tools\RefundInvoice;
use App\Enums\DemoMode;
use App\Models\User;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxSteps(6)]
class HelpdeskAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        public User $customer,
        public DemoMode $mode,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        You are the support assistant of a small online shop.
        You draft the reply to a support ticket written by {$this->customer->name}.
        Use the lookup_invoice tool for every invoice mentioned in the ticket,
        and the refund_invoice tool when the customer asks for a refund.
        Write the reply in French, in Markdown, addressed to the customer.
        PROMPT;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return list<Tool>
     */
    public function tools(): iterable
    {
        return match ($this->mode) {
            DemoMode::Vulnerable => [
                new LookupAnyInvoice,
                (new RefundInvoice($this->customer))->withoutApproval(),
            ],
            DemoMode::Secure => [
                new LookupInvoice($this->customer),
                new RefundInvoice($this->customer),
            ],
        };
    }
}
