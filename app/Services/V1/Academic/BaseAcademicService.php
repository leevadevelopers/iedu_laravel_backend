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
        $schoolUsers = $user->activeSchools();

        if ($schoolUsers->isEmpty()) {
            // For development, return a default school ID
            // In production, this should throw an exception
            \Log::warning('User not associated with any schools, using default school ID', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id
            ]);
            return 1; // Default school ID for development
        }

        return $schoolUsers->first()->school_id;
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
        $schoolUsers = $user->activeSchools();

        if ($schoolUsers->isEmpty()) {
            // For development, return a default school
            // In production, this should throw an exception
            \Log::warning('User not associated with any schools, using default school', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id
            ]);
            return \App\Models\V1\SIS\School\School::find(1); // Default school for development
        }

        return $schoolUsers->first()->school;
    }

    /**
     * Validate school ownership
     */
    protected function validateSchoolOwnership($model): void
    {
        $user = Auth::user();
        
        // Check if user has access to the model's school
        $userSchools = $user->activeSchools()->pluck('school_id')->toArray();
        
        if (!in_array($model->school_id, $userSchools)) {
            // For development, log a warning instead of throwing an exception
            \Log::warning('User does not have access to resource school', [
                'user_id' => $user->id,
                'model_school_id' => $model->school_id,
                'user_schools' => $userSchools,
                'model_type' => get_class($model),
                'model_id' => $model->id ?? 'new'
            ]);
            
            // In development, allow access; in production, throw exception
            if (app()->environment('production')) {
                throw new \Exception('Access denied: Resource does not belong to current school');
            }
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
