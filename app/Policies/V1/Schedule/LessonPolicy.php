<?php

namespace App\Policies\V1\Schedule;

use App\Models\User;
use App\Models\V1\Schedule\Lesson;
use App\Services\SchoolContextService;

class LessonPolicy
{
    protected SchoolContextService $schoolContextService;

    public function __construct(SchoolContextService $schoolContextService)
    {
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Determine whether the user can view any lessons.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->user_type, ['admin', 'teacher', 'academic_coordinator', 'principal', 'student', 'parent']);
    }

    /**
     * Determine whether the user can view the lesson.
     */
    public function view(User $user, Lesson $lesson): bool
    {
        // Check school ownership
        if ($lesson->school_id !== $this->schoolContextService->getCurrentSchoolId()) {
            return false;
        }

        // Admin, principal, academic coordinator can view all
        if (in_array($user->user_type, ['admin', 'principal', 'academic_coordinator'])) {
            return true;
        }

        // Teachers can view their own lessons
        if ($user->user_type === 'teacher') {
            return $lesson->teacher_id === $user->teacher?->id;
        }

        // Students can view their class lessons
        if ($user->user_type === 'student') {
            return $user->student->classes->contains($lesson->class_id);
        }

        // Parents can view their children's lessons
        if ($user->user_type === 'parent') {
            return $user->students->flatMap->classes->contains($lesson->class_id);
        }

        return false;
    }

    /**
     * Determine whether the user can create lessons.
     */
    public function create(User $user): bool
    {
        return in_array($user->user_type, ['admin', 'teacher', 'academic_coordinator', 'principal']);
    }

    /**
     * Determine whether the user can update the lesson.
     */
    public function update(User $user, Lesson $lesson): bool
    {
        // Check school ownership
        if ($lesson->school_id !== $this->schoolContextService->getCurrentSchoolId()) {
            return false;
        }

        // Admin, principal, academic coordinator can update all
        if (in_array($user->user_type, ['admin', 'principal', 'academic_coordinator'])) {
            return true;
        }

        // Teachers can update their own lessons
        if ($user->user_type === 'teacher' && $lesson->teacher_id === $user->teacher?->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the lesson.
     */
    public function delete(User $user, Lesson $lesson): bool
    {
        // Check school ownership
        if ($lesson->school_id !== $this->schoolContextService->getCurrentSchoolId()) {
            return false;
        }

        // Can't delete completed lessons
        if ($lesson->status === 'completed') {
            return false;
        }

        // Admin, principal, academic coordinator can delete
        if (in_array($user->user_type, ['admin', 'principal', 'academic_coordinator'])) {
            return true;
        }

        // Teachers can delete their own scheduled lessons
        if ($user->user_type === 'teacher' && $lesson->teacher_id === $user->teacher?->id) {
            return $lesson->status === 'scheduled';
        }

        return false;
    }

    /**
     * Determine whether the user can manage lesson state (start/complete/cancel).
     */
    public function manageState(User $user, Lesson $lesson): bool
    {
        // Check school ownership
        if ($lesson->school_id !== $this->schoolContextService->getCurrentSchoolId()) {
            return false;
        }

        // Admin, principal, academic coordinator can manage all
        if (in_array($user->user_type, ['admin', 'principal', 'academic_coordinator'])) {
            return true;
        }

        // Teachers can manage their own lessons
        if ($user->user_type === 'teacher' && $lesson->teacher_id === $user->teacher?->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can mark attendance.
     */
    public function markAttendance(User $user, Lesson $lesson): bool
    {
        return $this->manageState($user, $lesson);
    }

    /**
     * Determine whether the user can add content to lesson.
     */
    public function addContent(User $user, Lesson $lesson): bool
    {
        return $this->update($user, $lesson);
    }

    /**
     * Determine whether the user can generate QR code for attendance.
     */
    public function generateQR(User $user, Lesson $lesson): bool
    {
        return $this->markAttendance($user, $lesson);
    }

    /**
     * Determine whether the user can access lesson reports.
     */
    public function viewReports(User $user, Lesson $lesson): bool
    {
        return $this->view($user, $lesson);
    }
}
