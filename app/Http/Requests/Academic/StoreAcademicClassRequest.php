<?php

namespace App\Http\Requests\Academic;

class StoreAcademicClassRequest extends BaseAcademicRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        $schoolId = $this->getCurrentSchoolIdOrNull();
        $tenantId = $this->getCurrentTenantIdOrNull();

        return [
            'subject_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($schoolId, $tenantId) {
                    if (!$schoolId || !$tenantId) {
                        $fail('Unable to validate subject. Please ensure you are associated with a school.');
                        return;
                    }
                    $subject = \App\Models\V1\Academic\Subject::where('id', $value)
                        ->where('school_id', $schoolId)
                        ->where('tenant_id', $tenantId)
                        ->first();
                    if (!$subject) {
                        $fail('The selected subject is invalid.');
                    }
                }
            ],
            'academic_year_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($schoolId, $tenantId) {
                    if (!$schoolId || !$tenantId) {
                        $fail('Unable to validate academic year. Please ensure you are associated with a school.');
                        return;
                    }
                    $academicYear = \App\Models\V1\SIS\School\AcademicYear::where('id', $value)
                        ->where('school_id', $schoolId)
                        ->where('tenant_id', $tenantId)
                        ->first();
                    if (!$academicYear) {
                        $fail('The selected academic year is invalid.');
                    }
                }
            ],
            'academic_term_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($schoolId, $tenantId) {
                    if ($value && $schoolId && $tenantId) {
                        $academicTerm = \App\Models\V1\SIS\School\AcademicTerm::where('id', $value)
                            ->where('school_id', $schoolId)
                            ->where('tenant_id', $tenantId)
                            ->first();
                        if (!$academicTerm) {
                            $fail('The selected academic term is invalid.');
                        }
                    }
                }
            ],
            'name' => 'required|string|max:255',
            'section' => 'nullable|string|max:10',
            'class_code' => [
                'nullable',
                'string',
                'max:50',
                function ($attribute, $value, $fail) use ($schoolId) {
                    if ($value && $schoolId) {
                        $exists = \App\Models\V1\Academic\AcademicClass::where('class_code', $value)
                            ->where('school_id', $schoolId)
                            ->exists();
                        if ($exists) {
                            $fail('The class code has already been taken.');
                        }
                    }
                }
            ],
            'grade_level' => 'required|string|in:Pre-K,K,1,2,3,4,5,6,7,8,9,10,11,12',
            'max_students' => 'required|integer|min:1|max:50',
            'primary_teacher_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($schoolId, $tenantId) {
                    if ($value && $schoolId && $tenantId) {
                        $teacher = \App\Models\V1\Academic\Teacher::where('id', $value)
                            ->where('school_id', $schoolId)
                            ->where('tenant_id', $tenantId)
                            ->where('status', 'active')
                            ->first();
                        if (!$teacher) {
                            $fail('The selected primary teacher is invalid.');
                        }
                    }
                }
            ],
            'additional_teachers_json' => 'nullable|array',
            'additional_teachers_json.*.teacher_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($schoolId, $tenantId) {
                    if ($schoolId && $tenantId) {
                        $teacher = \App\Models\V1\Academic\Teacher::where('id', $value)
                            ->where('school_id', $schoolId)
                            ->where('tenant_id', $tenantId)
                            ->where('status', 'active')
                            ->first();
                        if (!$teacher) {
                            $fail('The selected teacher is invalid.');
                        }
                    }
                }
            ],
            'additional_teachers_json.*.role' => 'required|string|in:co-teacher,assistant,substitute',
            'schedule_json' => 'nullable|array',
            'schedule_json.*.day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'schedule_json.*.start_time' => 'required|date_format:H:i',
            'schedule_json.*.end_time' => 'required|date_format:H:i',
            'schedule_json.*.room' => 'nullable|string|max:50',
            'room_number' => 'nullable|string|max:50',
            'status' => 'nullable|in:planned,active,completed,cancelled'
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $schoolId = $this->getCurrentSchoolIdOrNull();
            $tenantId = $this->getCurrentTenantIdOrNull();

            if (!$schoolId || !$tenantId) {
                return;
            }

            // Validate that subject supports the grade level
            if ($this->filled('subject_id') && $this->filled('grade_level')) {
                $subject = \App\Models\V1\Academic\Subject::where('id', $this->subject_id)
                    ->where('school_id', $schoolId)
                    ->where('tenant_id', $tenantId)
                    ->first();

                if ($subject && !in_array($this->grade_level, $subject->grade_levels ?? [])) {
                    $validator->errors()->add('grade_level', 'The selected subject does not support this grade level.');
                }
            }

            // Validate academic term belongs to academic year
            if ($this->filled('academic_year_id') && $this->filled('academic_term_id')) {
                $term = \App\Models\V1\SIS\School\AcademicTerm::where('id', $this->academic_term_id)
                    ->where('school_id', $schoolId)
                    ->where('tenant_id', $tenantId)
                    ->first();

                if ($term && $term->academic_year_id != $this->academic_year_id) {
                    $validator->errors()->add('academic_term_id', 'The selected term does not belong to the specified academic year.');
                }
            }

            // Validate schedule_json end_time is after start_time
            if ($this->filled('schedule_json') && is_array($this->schedule_json)) {
                foreach ($this->schedule_json as $index => $schedule) {
                    if (isset($schedule['start_time']) && isset($schedule['end_time'])) {
                        $startTime = \Carbon\Carbon::createFromFormat('H:i', $schedule['start_time']);
                        $endTime = \Carbon\Carbon::createFromFormat('H:i', $schedule['end_time']);

                        if ($endTime->lte($startTime)) {
                            $validator->errors()->add("schedule_json.{$index}.end_time", 'The end time must be after the start time.');
                        }
                    }
                }
            }
        });
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        $schoolId = $this->getCurrentSchoolIdOrNull();
        $tenantId = $this->getCurrentTenantIdOrNull();

        // Add tenant_id and school_id from authenticated user
        $this->merge([
            'tenant_id' => $tenantId,
            'school_id' => $schoolId
        ]);

        // Generate class code if not provided
        if (!$this->filled('class_code') && $this->filled('subject_id') && $this->filled('grade_level') && $schoolId && $tenantId) {
            $subject = \App\Models\V1\Academic\Subject::where('id', $this->subject_id)
                ->where('school_id', $schoolId)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($subject) {
                $section = $this->section ?? 'A';
                $classCode = strtoupper($subject->code . '-' . $this->grade_level . '-' . $section);
                $this->merge(['class_code' => $classCode]);
            }
        }
    }
}
