<?php

use App\Ai\Agents\HelpdeskAgent;
use App\Ai\Tools\LookupAnyInvoice;
use App\Ai\Tools\LookupInvoice;
use App\Ai\Tools\RefundInvoice;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\User;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->mallory = User::factory()->create(['name' => 'Mallory']);
    $this->bob = User::factory()->create(['name' => 'Bob']);

    $this->malloryInvoice = Invoice::factory()->for($this->mallory)->create(['number' => 'INV-2001', 'iban' => 'BE71 0961 2345 6769']);
    $this->bobInvoice = Invoice::factory()->for($this->bob)->create(['number' => 'INV-1042', 'iban' => 'BE68 5390 0754 7034']);
});

test('the vulnerable lookup tool leaks another customer\'s IBAN', function () {
    $result = (new LookupAnyInvoice)->handle(new Request(['number' => 'INV-1042']));

    expect($result)->toContain('BE68 5390 0754 7034');
});

test('the secure lookup tool returns the acting customer\'s own invoice', function () {
    $result = (new LookupInvoice($this->mallory))->handle(new Request(['number' => 'INV-2001']));

    expect($result)->toContain('BE71 0961 2345 6769');
});

test('the secure lookup tool denies another customer\'s invoice', function () {
    $result = (new LookupInvoice($this->mallory))->handle(new Request(['number' => 'INV-1042']));

    expect($result)->toContain('Access denied')->not->toContain('BE68 5390 0754 7034');
});

test('the refund tool refunds the acting customer\'s invoice', function () {
    (new RefundInvoice($this->mallory))->handle(new Request(['number' => 'INV-2001']));

    expect($this->malloryInvoice->fresh()->isRefunded())->toBeTrue();
});

test('the refund tool refuses to refund another customer\'s invoice', function () {
    $result = (new RefundInvoice($this->mallory))->handle(new Request(['number' => 'INV-1042']));

    expect($result)->toContain('Refund refused')
        ->and($this->bobInvoice->fresh()->isRefunded())->toBeFalse();
});

test('the refund tool refuses to refund an invoice twice', function () {
    Invoice::factory()->for($this->mallory)->refunded()->create(['number' => 'INV-2002']);

    $result = (new RefundInvoice($this->mallory))->handle(new Request(['number' => 'INV-2002']));

    expect($result)->toContain('Refund refused');
});

test('the refund tool asks a human to approve the refund amount', function () {
    $this->malloryInvoice->update(['amount_cents' => 89_900]);

    $approval = (new RefundInvoice($this->mallory))->shouldRequestApproval(new Request(['number' => 'INV-2001']));

    expect($approval->reason)->toContain('899,00')->toContain('INV-2001');
});

test('the refund tool runs without approval when the agent opts out', function () {
    $approval = (new RefundInvoice($this->mallory))->withoutApproval()->shouldRequestApproval(new Request(['number' => 'INV-2001']));

    expect($approval)->toBeNull();
});

test('the reply command sends the ticket to the agent and prints the draft', function () {
    HelpdeskAgent::fake(['Bonjour Mallory, voici votre réponse.']);
    $ticket = Ticket::factory()->for($this->mallory)->create(['body' => 'Ma facture INV-2001 a été prélevée deux fois.']);

    $this->artisan('helpdesk:reply', ['ticket' => $ticket->id])
        ->expectsOutputToContain('VULNERABLE')
        ->expectsOutputToContain('Bonjour Mallory, voici votre réponse.')
        ->assertSuccessful();

    HelpdeskAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('Ma facture INV-2001 a été prélevée deux fois.'));
});

test('the reply command sends the reviewer\'s rejection back to the agent', function () {
    HelpdeskAgent::fake([
        AgentResponse::fakeWithPendingApprovals([
            new PendingApproval(id: 'call_refund', tool: 'refund_invoice', arguments: ['number' => 'INV-2001'], reason: 'Remboursement irréversible'),
        ]),
    ]);
    $ticket = Ticket::factory()->for($this->mallory)->create();

    $this->artisan('helpdesk:reply', ['ticket' => $ticket->id, '--secure' => true])
        ->expectsConfirmation('Remboursement irréversible', 'no')
        ->expectsOutputToContain('Draft reply')
        ->assertSuccessful();

    HelpdeskAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->hasApprovalDecisions()
        && ! $prompt->approvalDecisions->get('call_refund')->isApproved());
});
