<?php

namespace App\Policies\Library;

use App\Models\V1\Library\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['library.reservations.view', 'library.manage']);
    }

    public function view(User $user, Reservation $reservation): bool
    {
        if ($user->hasAnyPermission(['library.reservations.view', 'library.manage'])) {
            return true;
        }

        return $reservation->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyPermission(['library.reservations.create', 'library.manage']);
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        if ($user->hasAnyPermission(['library.reservations.manage', 'library.manage'])) {
            return true;
        }

        return $reservation->user_id === $user->id;
    }
}
