<?php

namespace App\Http\Requests\Assessment;

class UpdateAssessmentRequest extends BaseAssessmentRequest
{
    /**
     * Prepare the data for validation and convert teacher_id if needed
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('teacher_id') && $this->teacher_id) {
            $tenantId = $this->getCurrentTenantIdOrNull();

            if ($tenantId) {
                // Try to find teacher by ID first (in case user sends teacher.id instead of user_id)
                $teacher = \App\Models\V1\Academic\Teacher::where('id', $this->teacher_id)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->first();

                if ($teacher && $teacher->user_id) {
                    // Convert teacher ID to user_id
                    $this->merge([
                        'teacher_id' => $teacher->user_id
                    ]);
                }
            }
        }
    }

    public function rules(): array
    {
        $tenantId = $this->getCurrentTenantIdOrNull();
        $schoolId = $this->getCurrentSchoolIdOrNull();

        return [
            'term_id' => [
                'sometimes',
                'integer',
                function ($attribute, $value, $fail) use ($tenantId) {
                    if ($value && $tenantId) {
                        $exists = \App\Models\Assessment\AssessmentTerm::where('id', $value)
                            ->where('tenant_id', $tenantId)
                            ->exists();
                        if (!$exists) {
                            $fail('The selected term is invalid.');
                        }
                    }
                },
            ],
            'subject_id' => [
                'sometimes',
                'integer',
                function ($attribute, $value, $fail) use ($schoolId) {
                    if ($value && $schoolId) {
                        $exists = \App\Models\V1\Academic\Subject::where('id', $value)
                            ->where('school_id', $schoolId)
                            ->exists();
                        if (!$exists) {
                            $fail('The selected subject is invalid.');
                        }
                    }
                },
            ],
            'class_id' => [
                'sometimes',
                'integer',
                function ($attribute, $value, $fail) use ($schoolId) {
                    if ($value && $schoolId) {
                        $exists = \App\Models\V1\Academic\AcademicClass::where('id', $value)
                            ->where('school_id', $schoolId)
                            ->exists();
                        if (!$exists) {
                            $fail('The selected class is invalid.');
                        }
                    }
                },
            ],
            'teacher_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($tenantId) {
                    if ($value && $tenantId) {
                        // Check if user exists and is a teacher
                        $user = \App\Models\User::where('id', $value)
                            ->where('user_type', 'teacher')
                            ->first();

                        if (!$user) {
                            $fail('The selected teacher id is invalid.');
                            return;
                        }

                        // Verify teacher belongs to tenant through teachers table
                        $teacher = \App\Models\V1\Academic\Teacher::where('user_id', $value)
                            ->where('tenant_id', $tenantId)
                            ->where('status', 'active')
                            ->first();

                        if (!$teacher) {
                            $fail('The selected teacher id is invalid.');
                        }
                    }
                },
            ],
            'type_id' => [
                'sometimes',
                'integer',
                function ($attribute, $value, $fail) use ($tenantId) {
                    if ($value && $tenantId) {
                        $exists = \App\Models\Assessment\AssessmentType::where('id', $value)
                            ->where('tenant_id', $tenantId)
                            ->exists();
                        if (!$exists) {
                            $fail('The selected type is invalid.');
                        }
                    }
                },
            ],
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'scheduled_date' => 'nullable|date',
            'start_time' => 'nullable|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:1',
            'submission_deadline' => 'nullable|date',
            'total_marks' => 'sometimes|numeric|min:0',
            'weight' => 'nullable|numeric|min:0|max:100',
            'visibility' => 'nullable|in:public,private,tenant',
            'allow_upload_submissions' => 'nullable|boolean',
            'status' => 'nullable|in:draft,scheduled,in_progress,completed,cancelled',
            'metadata' => 'nullable|array',
        ];
    }
}

