<?php

namespace App\Ai\Tools;

use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * ⚠️ VULNERABLE ON PURPOSE — it never asks who the agent is acting for.
 */
class LookupAnyInvoice implements Tool
{
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
