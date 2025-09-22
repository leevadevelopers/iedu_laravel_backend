<?php

namespace App\Services\V1\Schedule;

use Illuminate\Support\Facades\Auth;

abstract class BaseScheduleService
{
    protected function getCurrentSchoolId(): int
    {
        $user = Auth::user();

        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        $school = $user->activeSchools()->first();

        if (!$school) {
            throw new \Exception('User is not associated with any schools');
        }

        return $school->id;
    }

    protected function validateSchoolOwnership($model): void
    {
        if ($model->school_id !== $this->getCurrentSchoolId()) {
            throw new \Exception('Access denied: Resource does not belong to current school');
        }
    }
}
