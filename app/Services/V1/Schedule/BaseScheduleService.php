<?php

namespace App\Services\V1\Schedule;

use App\Models\V1\SIS\School\School;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

abstract class BaseScheduleService
{
    /**
     * Get current school ID from user's school_users relationship
     * Only returns schools that belong to the user's current tenant
     */
    protected function getCurrentSchoolId(): int
    {
        $user = Auth::user();

        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            throw new \Exception('User is not associated with any tenant');
        }

        // Get active schools for the user that belong to the current tenant
        $schoolUsers = $user->activeSchools();

        if ($schoolUsers->isEmpty()) {
            throw new \Exception('User is not associated with any schools');
        }

        // Filter schools by tenant
        $schoolIds = $schoolUsers->pluck('school_id')->toArray();
        $school = School::whereIn('id', $schoolIds)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$school) {
            throw new \Exception('User is not associated with any schools in the current tenant');
        }

        return $school->id;
    }

    /**
     * Get current tenant ID from authenticated user
     */
    protected function getCurrentTenantId(): ?int
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        // Try tenant_id attribute first
        if (isset($user->tenant_id) && $user->tenant_id) {
            return $user->tenant_id;
        }

        // Try getCurrentTenant method
        if (method_exists($user, 'getCurrentTenant')) {
            $currentTenant = $user->getCurrentTenant();
            if ($currentTenant) {
                return $currentTenant->id;
            }
        }

        return null;
    }

    /**
     * Verify that a school_id belongs to the user's tenant
     */
    protected function verifySchoolAccess(int $schoolId): bool
    {
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return false;
        }

        // Check if school belongs to user's tenant
        $school = School::where('id', $schoolId)
            ->where('tenant_id', $tenantId)
            ->exists();

        return $school;
    }

    /**
     * Validate school and tenant ownership
     */
    protected function validateSchoolOwnership($model): void
    {
        $user = Auth::user();

        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            throw new \Exception('User is not associated with any tenant');
        }

        // Check tenant ownership
        if (isset($model->tenant_id) && $model->tenant_id !== $tenantId) {
            throw new \Exception('Access denied: Resource does not belong to current tenant');
        }

        // Check if user has access to the model's school
        $userSchools = $user->activeSchools()->pluck('school_id')->toArray();

        if (!in_array($model->school_id, $userSchools)) {
            Log::warning('User does not have access to resource school', [
                'user_id' => $user->id,
                'model_school_id' => $model->school_id,
                'user_schools' => $userSchools,
                'model_type' => get_class($model),
                'model_id' => $model->id ?? 'new'
            ]);

            if (app()->environment('production')) {
                throw new \Exception('Access denied: Resource does not belong to current school');
            }
        }

        // Verify school belongs to tenant
        if (!$this->verifySchoolAccess($model->school_id)) {
            throw new \Exception('Access denied: School does not belong to current tenant');
        }
    }
}
