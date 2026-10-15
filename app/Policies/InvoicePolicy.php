<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Determine whether the user can see the invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $invoice->user()->is($user);
    }

    /**
     * Determine whether the user can get the invoice refunded.
     */
    public function refund(User $user, Invoice $invoice): bool
    {
        return $invoice->user()->is($user) && ! $invoice->isRefunded();
    }
}
