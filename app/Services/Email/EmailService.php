<?php

namespace App\Services\Email;

use App\Mail\UserWelcomeMail;
use App\Mail\StudentWelcomeMail;
use App\Mail\TeacherWelcomeMail;
use App\Mail\ParentStudentRegisteredMail;
use App\Models\User;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\SIS\Student\FamilyRelationship;
use App\Models\V1\Academic\Teacher;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    /**
     * Send welcome email to user
     */
    public function sendUserWelcomeEmail(User $user): bool
    {
        try {
            // Only send if user has email
            if (!$this->hasEmail($user)) {
                Log::info('User welcome email skipped - no email address', [
                    'user_id' => $user->id,
                ]);
                return false;
            }

            Mail::to($user->identifier)->send(new UserWelcomeMail($user));

            Log::info('User welcome email sent', [
                'user_id' => $user->id,
                'email' => $user->identifier,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send user welcome email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send welcome email to student
     */
    public function sendStudentWelcomeEmail(Student $student): bool
    {
        try {
            // Get student's user if exists
            $user = $student->user;
            if (!$user || !$this->hasEmail($user)) {
                Log::info('Student welcome email skipped - no email address', [
                    'student_id' => $student->id,
                ]);
                return false;
            }

            Mail::to($user->identifier)->send(new StudentWelcomeMail($student, $user));

            Log::info('Student welcome email sent', [
                'student_id' => $student->id,
                'email' => $user->identifier,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send student welcome email', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send welcome email to teacher with credentials
     */
    public function sendTeacherWelcomeEmail(Teacher $teacher, string $password): bool
    {
        try {
            // Get teacher's user if exists
            $user = $teacher->user;
            if (!$user || !$this->hasEmail($user)) {
                Log::info('Teacher welcome email skipped - no email address', [
                    'teacher_id' => $teacher->id,
                ]);
                return false;
            }

            Mail::to($user->identifier)->send(new TeacherWelcomeMail($teacher, $user, $password));

            Log::info('Teacher welcome email sent', [
                'teacher_id' => $teacher->id,
                'email' => $user->identifier,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send teacher welcome email', [
                'teacher_id' => $teacher->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send notification email to parents when student is registered
     */
    public function sendParentNotificationEmails(Student $student): int
    {
        $sentCount = 0;

        try {
            // Get all active family relationships for this student
            $relationships = FamilyRelationship::where('student_id', $student->id)
                ->where('status', 'active')
                ->with('guardian')
                ->get();

            foreach ($relationships as $relationship) {
                $guardian = $relationship->guardian;
                
                if (!$guardian || !$this->hasEmail($guardian)) {
                    continue;
                }

                try {
                    Mail::to($guardian->identifier)->send(
                        new ParentStudentRegisteredMail($student, $guardian, $relationship)
                    );

                    $sentCount++;

                    Log::info('Parent notification email sent', [
                        'student_id' => $student->id,
                        'guardian_id' => $guardian->id,
                        'relationship_type' => $relationship->relationship_type,
                        'email' => $guardian->identifier,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send parent notification email', [
                        'student_id' => $student->id,
                        'guardian_id' => $guardian->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $sentCount;
        } catch (\Exception $e) {
            Log::error('Failed to send parent notification emails', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);
            return $sentCount;
        }
    }

    /**
     * Check if user has email
     */
    protected function hasEmail(User $user): bool
    {
        if (!$user->identifier) {
            return false;
        }

        // Check if identifier is an email
        return filter_var($user->identifier, FILTER_VALIDATE_EMAIL) !== false;
    }
}

