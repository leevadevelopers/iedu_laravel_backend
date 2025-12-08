<?php

namespace App\Http\Requests\V1\Schedule;

use Illuminate\Support\Facades\Log;

class StoreScheduleRequest extends BaseScheduleRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->getCurrentTenantIdOrNull();
        $schoolId = $this->getCurrentSchoolIdOrNull();

        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',

            // Associations - validate with tenant_id and school_id
            'subject_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($tenantId, $schoolId) {
                    if ($tenantId && $schoolId) {
                        $exists = \App\Models\V1\Academic\Subject::where('id', $value)
                            ->where('tenant_id', $tenantId)
                            ->where('school_id', $schoolId)
                            ->exists();
                        if (!$exists) {
                            $fail('O disciplina selecionado é inválido.');
                        }
                    }
                },
            ],
            'class_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($tenantId, $schoolId) {
                    if ($tenantId && $schoolId) {
                        $exists = \App\Models\V1\Academic\AcademicClass::where('id', $value)
                            ->where('tenant_id', $tenantId)
                            ->where('school_id', $schoolId)
                            ->exists();
                        if (!$exists) {
                            $fail('O turma selecionado é inválido.');
                        }
                    }
                },
            ],
            'teacher_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($tenantId, $schoolId) {
                    if ($value && $tenantId && $schoolId) {
                        $exists = \App\Models\V1\Academic\Teacher::where('id', $value)
                            ->where('tenant_id', $tenantId)
                            ->where('school_id', $schoolId)
                            ->exists();
                        if (!$exists) {
                            $fail('O professor selecionado é inválido.');
                        }
                    }
                },
            ],
            'academic_year_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($tenantId, $schoolId) {
                    if ($value && $tenantId && $schoolId) {
                        $exists = \App\Models\V1\SIS\School\AcademicYear::where('id', $value)
                            ->where('tenant_id', $tenantId)
                            ->where('school_id', $schoolId)
                            ->exists();
                        if (!$exists) {
                            $fail('O ano letivo selecionado é inválido.');
                        }
                    }
                },
            ],
            'academic_term_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($tenantId, $schoolId) {
                    if ($value && $tenantId && $schoolId) {
                        $exists = \App\Models\V1\SIS\School\AcademicTerm::where('id', $value)
                            ->where('tenant_id', $tenantId)
                            ->where('school_id', $schoolId)
                            ->exists();
                        if (!$exists) {
                            $fail('O período letivo selecionado é inválido.');
                        }
                    }
                },
            ],
            'classroom' => 'nullable|string|max:50',

            // Timing
            'period' => 'required|in:morning,afternoon,evening,night',
            'day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',

            // Date range
            'start_date' => 'required|date|before:end_date',
            'end_date' => 'required|date|after:start_date',
            'recurrence_pattern' => 'nullable|array',

            // Configuration
            'status' => 'nullable|in:active,suspended,cancelled,completed',
            'is_online' => 'boolean',
            'online_meeting_url' => 'nullable|url|max:500',
            'configuration_json' => 'nullable|array',

            // Auto-generation
            'auto_generate_lessons' => 'boolean'
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate minimum duration
            if ($this->filled('start_time') && $this->filled('end_time')) {
                // Use strict time parsing to avoid date/timezone side-effects
                try {
                    $start = \Carbon\Carbon::createFromFormat('H:i', $this->start_time)->seconds(0);
                    $end = \Carbon\Carbon::createFromFormat('H:i', $this->end_time)->seconds(0);
                } catch (\Exception $e) {
                    // If parsing fails, the base rules will already flag format errors
                    return;
                }
                $duration = $start->diffInMinutes($end, false);
                Log::debug('Schedule duration validation', [
                    'start_time' => $this->start_time,
                    'end_time' => $this->end_time,
                    'parsed_start' => $start->toTimeString(),
                    'parsed_end' => $end->toTimeString(),
                    'duration_minutes' => $duration,
                ]);

                if ($duration < 30) {
                    $validator->errors()->add('end_time', 'A duração mínima da aula deve ser de 30 minutos.');
                }

                if ($duration > 240) { // 4 hours
                    $validator->errors()->add('end_time', 'A duração máxima da aula deve ser de 4 horas.');
                }
            }

            // Validate date range
            if ($this->filled('start_date') && $this->filled('end_date')) {
                $startDate = \Carbon\Carbon::parse($this->start_date);
                $endDate = \Carbon\Carbon::parse($this->end_date);
                $daysDiff = $endDate->diffInDays($startDate);

                if ($daysDiff > 365) {
                    $validator->errors()->add('end_date', 'O período do horário não pode exceder 1 ano.');
                }
            }

            // Validate online meeting URL if online
            if ($this->boolean('is_online') && !$this->filled('online_meeting_url')) {
                $validator->errors()->add('online_meeting_url', 'URL da reunião é obrigatória para aulas online.');
            }
        });
    }
}
