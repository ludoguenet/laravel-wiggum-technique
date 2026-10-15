<?php

namespace App\Ai\Tools;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * SECURE — the tool knows who the agent is acting for and asks the InvoicePolicy.
 */
class LookupInvoice implements Tool
{
    public function __construct(private User $user) {}

    public function name(): string
    {
        return 'lookup_invoice';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Look up an invoice by its number (e.g. INV-1001) and return its details.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $invoice = Invoice::where('number', $request['number'])->first();

        if ($invoice === null) {
            return "Invoice {$request['number']} not found.";
        }

        if (Gate::forUser($this->user)->denies('view', $invoice)) {
            return "Access denied: invoice {$invoice->number} does not belong to the current customer.";
        }

        return "Invoice {$invoice->number}: {$invoice->amount_cents} cents, billing address {$invoice->billing_address}, IBAN {$invoice->iban}.";
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'number' => $schema->string()->description('The invoice number, e.g. INV-1001.')->required(),
        ];
    }
}
