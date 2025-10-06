<?php

namespace App\Policies\Financial;

use App\Models\V1\Financial\Fee;
use App\Models\User;

class FeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['finance.fees.view', 'finance.manage']);
    }

    public function view(User $user, Fee $fee): bool
    {
        return $user->hasAnyPermission(['finance.fees.view', 'finance.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['finance.fees.create', 'finance.manage']);
    }

    public function update(User $user, Fee $fee): bool
    {
        return $user->hasAnyPermission(['finance.fees.update', 'finance.manage']);
    }

    public function delete(User $user, Fee $fee): bool
    {
        return $user->hasAnyPermission(['finance.fees.delete', 'finance.manage']);
    }

    public function apply(User $user): bool
    {
        return $user->hasAnyPermission(['finance.fees.apply', 'finance.manage']);
    }
}
