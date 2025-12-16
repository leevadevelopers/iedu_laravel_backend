<?php

namespace App\Http\Requests\Assessment;

class UpdateAssessmentRequest extends BaseAssessmentRequest
{
    /**
     * Prepare the data for validation and convert teacher_id if needed
     */
    protected function prepareForValidation(): void
    {
        // Convert term_id to academic_term_id for backward compatibility
        if ($this->has('term_id') && !$this->has('academic_term_id')) {
            $this->merge(['academic_term_id' => $this->term_id]);
        }

        if ($this->has('teacher_id') && $this->teacher_id) {
            $tenantId = $this->getCurrentTenantIdOrNull();

            if ($tenantId) {
                // Convert teacher_id to integer
                $teacherIdValue = is_numeric($this->teacher_id) ? (int)$this->teacher_id : $this->teacher_id;
                
                // First, check if it's already a user_id by checking if a teacher exists with this user_id
                $teacherByUserId = \App\Models\V1\Academic\Teacher::where('user_id', $teacherIdValue)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->first();

                if ($teacherByUserId) {
                    // It's already a user_id, use it as is
                    $this->merge([
                        'teacher_id' => $teacherByUserId->user_id
                    ]);
                } else {
                    // Try to find teacher by ID (in case user sends teacher.id instead of user_id)
                    $teacher = \App\Models\V1\Academic\Teacher::where('id', $teacherIdValue)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->first();

                if ($teacher && $teacher->user_id) {
                    // Convert teacher ID to user_id
                    $this->merge([
                        'teacher_id' => $teacher->user_id
                    ]);
                    } else {
                        // If teacher not found by id, try to use the value as user_id directly
                        // The validation rule will check if it's valid
                        $this->merge([
                            'teacher_id' => $teacherIdValue
                        ]);
                    }
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
                        // Check if it's a valid academic term (preferred) or assessment term (backward compat)
                        $academicTermExists = \App\Models\V1\SIS\School\AcademicTerm::where('id', $value)
                            ->where('school_id', $this->getCurrentSchoolIdOrNull())
                            ->exists();
                        $assessmentTermExists = \App\Models\Assessment\AssessmentTerm::where('id', $value)
                            ->where('tenant_id', $tenantId)
                            ->exists();
                        if (!$academicTermExists && !$assessmentTermExists) {
                            $fail('The selected term is invalid.');
                        }
                    }
                },
            ],
            'academic_term_id' => [
                'sometimes',
                'integer',
                'exists:academic_terms,id',
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
                        // Verify teacher exists in teachers table with the given user_id
                        // The teachers table has user_id column that references users table
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

