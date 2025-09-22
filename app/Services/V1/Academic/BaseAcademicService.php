<?php

namespace App\Services\V1\Academic;

use Illuminate\Support\Facades\Auth;

abstract class BaseAcademicService
{
    /**
     * Get current school ID from user's school_users relationship
     */
    protected function getCurrentSchoolId(): int
    {
        $user = Auth::user();

        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        // Get the first active school for the user
        $school = $user->activeSchools()->first();

        if (!$school) {
            throw new \Exception('User is not associated with any schools');
        }

        return $school->id;
    }

    /**
     * Get current school model
     */
    protected function getCurrentSchool()
    {
        $user = Auth::user();

        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        // Get the first active school for the user
        $school = $user->activeSchools()->first();

        if (!$school) {
            throw new \Exception('User is not associated with any schools');
        }

        return $school;
    }

    /**
     * Validate school ownership
     */
    protected function validateSchoolOwnership($model): void
    {
        $userSchoolId = $this->getCurrentSchoolId();

        if ($model->school_id !== $userSchoolId) {
            throw new \Exception('Access denied: Resource does not belong to current school');
        }
    }

    /**
     * Apply school scope to query
     */
    protected function applySchoolScope($query)
    {
        return $query->where('school_id', $this->getCurrentSchoolId());
    }
}
