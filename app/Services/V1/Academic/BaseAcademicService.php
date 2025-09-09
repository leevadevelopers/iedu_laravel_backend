<?php

namespace App\Services\V1\Academic;

use App\Services\SchoolContextService;

abstract class BaseAcademicService
{
    protected SchoolContextService $schoolContextService;

    public function __construct(SchoolContextService $schoolContextService)
    {
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Get current school ID
     */
    protected function getCurrentSchoolId(): int
    {
        return $this->schoolContextService->getCurrentSchoolId();
    }

    /**
     * Validate school ownership
     */
    protected function validateSchoolOwnership($model): void
    {
        if ($model->school_id !== $this->getCurrentSchoolId()) {
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
