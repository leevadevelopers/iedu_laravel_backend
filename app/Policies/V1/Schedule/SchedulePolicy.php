<?php

namespace App\Policies\V1\Schedule;

use App\Models\User;
use App\Models\V1\Schedule\Schedule;
use App\Services\SchoolContextService;

class SchedulePolicy
{
    protected SchoolContextService $schoolContextService;

    public function __construct(SchoolContextService $schoolContextService)
    {
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Determine whether the user can view any schedules.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->user_type, ['admin', 'teacher', 'academic_coordinator', 'principal']);
    }

    /**
     * Determine whether the user can view the schedule.
     */
    public function view(User $user, Schedule $schedule): bool
    {
        // Check school ownership
        if ($schedule->school_id !== $this->schoolContextService->getCurrentSchoolId()) {
            return false;
        }

        // Admin, principal, academic coordinator can view all
        if (in_array($user->user_type, ['admin', 'principal', 'academic_coordinator'])) {
            return true;
        }

        // Teachers can view their own schedules
        if ($user->user_type === 'teacher') {
            return $schedule->teacher_id === $user->teacher?->id;
        }

        // Students can view their class schedules
        if ($user->user_type === 'student') {
            return $user->student->classes->contains($schedule->class_id);
        }

        // Parents can view their children's schedules
        if ($user->user_type === 'parent') {
            return $user->students->flatMap->classes->contains($schedule->class_id);
        }

        return false;
    }

    /**
     * Determine whether the user can create schedules.
     */
    public function create(User $user): bool
    {
        return in_array($user->user_type, ['admin', 'academic_coordinator', 'principal']);
    }

    /**
     * Determine whether the user can update the schedule.
     */
    public function update(User $user, Schedule $schedule): bool
    {
        // Check school ownership
        if ($schedule->school_id !== $this->schoolContextService->getCurrentSchoolId()) {
            return false;
        }

        // Admin, principal, academic coordinator can update all
        if (in_array($user->user_type, ['admin', 'principal', 'academic_coordinator'])) {
            return true;
        }

        // Teachers can update their own schedules with restrictions
        if ($user->user_type === 'teacher' && $schedule->teacher_id === $user->teacher?->id) {
            // Teachers can only update certain fields (not timing or core assignments)
            return true; // Additional field-level restrictions should be in the request validation
        }

        return false;
    }

    /**
     * Determine whether the user can delete the schedule.
     */
    public function delete(User $user, Schedule $schedule): bool
    {
        // Check school ownership
        if ($schedule->school_id !== $this->schoolContextService->getCurrentSchoolId()) {
            return false;
        }

        // Only admin, principal, academic coordinator can delete
        return in_array($user->user_type, ['admin', 'principal', 'academic_coordinator']);
    }

    /**
     * Determine whether the user can generate lessons from schedule.
     */
    public function generateLessons(User $user, Schedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }
}
