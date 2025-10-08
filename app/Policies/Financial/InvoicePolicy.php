<?php

namespace App\Policies\Financial;

use App\Models\V1\Financial\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['finance.invoices.view', 'finance.manage']);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->hasAnyPermission(['finance.invoices.view', 'finance.manage'])) {
            return true;
        }

        // Users can view their own invoices
        return $invoice->billable_id === $user->id &&
               $invoice->billable_type === User::class;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['finance.invoices.create', 'finance.manage']);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if (!$user->hasAnyPermission(['finance.invoices.update', 'finance.manage'])) {
            return false;
        }

        // Cannot update paid invoices
        return !in_array($invoice->status, ['paid', 'cancelled']);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        if (!$user->hasAnyPermission(['finance.invoices.delete', 'finance.manage'])) {
            return false;
        }

        // Can only delete draft invoices
        return $invoice->status === 'draft';
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        if (!$user->hasAnyPermission(['finance.invoices.issue', 'finance.manage'])) {
            return false;
        }

        return $invoice->status === 'draft';
    }

    public function viewReports(User $user): bool
    {
        return $user->hasAnyPermission(['finance.reports.view', 'finance.manage']);
    }
}
