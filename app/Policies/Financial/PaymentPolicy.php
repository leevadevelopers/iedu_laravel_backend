<?php

namespace App\Policies\Financial;

use App\Models\V1\Financial\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['finance.payments.view', 'finance.manage']);
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->hasAnyPermission(['finance.payments.view', 'finance.manage'])) {
            return true;
        }

        // Users can view payments for their own invoices
        return $payment->invoice->billable_id === $user->id &&
               $payment->invoice->billable_type === User::class;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['finance.payments.create', 'finance.manage']);
    }
}
