<?php

namespace App\Policies\Library;

use App\Models\V1\Library\Incident;
use App\Models\User;

class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['library.incidents.view', 'library.manage']);
    }

    public function view(User $user, Incident $incident): bool
    {
        if ($user->hasAnyPermission(['library.incidents.view', 'library.manage'])) {
            return true;
        }

        return $incident->reporter_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['library.incidents.create', 'library.manage']);
    }

    public function resolve(User $user, Incident $incident): bool
    {
        return $user->hasAnyPermission(['library.incidents.resolve', 'library.manage']);
    }
}
