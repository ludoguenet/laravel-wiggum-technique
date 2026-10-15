    <?php

    namespace App\Ai\Tools;

    use App\Models\Invoice;
    use App\Models\User;
    use Illuminate\Contracts\JsonSchema\JsonSchema;
    use Illuminate\Support\Facades\Gate;
    use Illuminate\Support\Number;
    use Laravel\Ai\Approvals\Approval;
    use Laravel\Ai\Concerns\InteractsWithApprovals;
    use Laravel\Ai\Contracts\Approvable;
    use Laravel\Ai\Contracts\Tool;
    use Laravel\Ai\Tools\Request;
    use Stringable;

    /**
     * Irreversible action: a human has to approve the call before handle() runs.
     */
    class RefundInvoice implements Approvable, Tool
    {
        use InteractsWithApprovals;

        public function __construct(private User $user) {}

        public function name(): string
        {
            return 'refund_invoice';
        }

        /**
         * Get the description of the tool's purpose.
         */
        public function description(): Stringable|string
        {
            return 'Refund an invoice of the current customer, given its number (e.g. INV-1001).';
        }

        /**
         * Execute the tool.
         */
        public function handle(Request $request): Stringable|string
        {
            $invoice = Invoice::where('number', $request['number'])->first();

            if ($invoice === null || Gate::forUser($this->user)->denies('refund', $invoice)) {
                return "Refund refused for invoice {$request['number']}.";
            }

            $invoice->update(['refunded_at' => now()]);

            return "Invoice {$invoice->number} refunded.";
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

        /**
         * Determine whether the tool needs approval for the given request.
         */
        protected function needsApproval(Request $request): Approval|bool
        {
            $invoice = Invoice::where('number', $request['number'])->first();

            $amount = Number::currency(($invoice?->amount_cents ?? 0) / 100, 'EUR', 'fr');

            return Approval::required("Remboursement irréversible de {$amount} ({$request['number']}).");
        }
    }
