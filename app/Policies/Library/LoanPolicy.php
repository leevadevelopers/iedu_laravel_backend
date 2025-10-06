<?php

namespace App\Policies\Library;

use App\Models\V1\Library\Loan;
use App\Models\User;

class LoanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['library.loans.view', 'library.manage']);
    }

    public function view(User $user, Loan $loan): bool
    {
        if ($user->hasAnyPermission(['library.loans.view', 'library.manage'])) {
            return true;
        }

        // Users can view their own loans
        return $loan->borrower_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['library.loans.create', 'library.manage', 'library.loans.request']);
    }

    public function return(User $user, Loan $loan): bool
    {
        if ($user->hasAnyPermission(['library.loans.manage', 'library.manage'])) {
            return true;
        }

        // Users can return their own loans
        return $loan->borrower_id === $user->id;
    }

    public function delete(User $user, Loan $loan): bool
    {
        return $user->hasAnyPermission(['library.loans.delete', 'library.manage']);
    }
}
