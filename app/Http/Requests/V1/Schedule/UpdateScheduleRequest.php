<?php

namespace App\Http\Requests\V1\Schedule;

use Illuminate\Support\Facades\Log;

class UpdateScheduleRequest extends BaseScheduleRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     * This ensures all request data is available even if validated() doesn't return it.
     */
    protected function prepareForValidation(): void
    {
        // Log raw request data for debugging
        Log::debug('UpdateScheduleRequest prepareForValidation', [
            'all' => $this->all(),
            'input' => $this->input(),
            'json' => method_exists($this, 'json') ? $this->json()->all() : [],
            'content' => substr($this->getContent(), 0, 500),
            'method' => $this->method(),
            'content_type' => $this->header('Content-Type')
        ]);
    }

    /**
     * Get all validated data including fields validated by closures
     * Override to ensure all data is returned
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // If validated is empty but we have data, return all data that matches rules
        if (empty($validated) && !empty($this->all())) {
            $rules = $this->rules();
            $allData = $this->all();

            // Filter data to only include fields that have rules
            $filtered = [];
            foreach (array_keys($rules) as $field) {
                if (isset($allData[$field])) {
                    $filtered[$field] = $allData[$field];
                }
            }

            Log::debug('UpdateScheduleRequest validated override', [
                'parent_validated' => $validated,
                'all_data' => $allData,
                'filtered_by_rules' => $filtered
            ]);

            if (!empty($filtered)) {
                return $key ? ($filtered[$key] ?? $default) : $filtered;
            }
        }

        return $validated;
    }

    /**
     * Get all request data including JSON
     */
    public function getAllData(): array
    {
        $data = $this->all();

        // Try to get from JSON if available
        if ($this->isJson() && method_exists($this, 'json')) {
            $jsonData = $this->json()->all();
            $data = array_merge($data, $jsonData);
        }

        // Also try raw content
        if (empty($data)) {
            $content = $this->getContent();
            if (!empty($content)) {
                $decoded = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }

        return $data;
    }

    public function rules(): array
    {
        $tenantId = $this->getCurrentTenantIdOrNull();
        $schoolId = $this->getCurrentSchoolIdOrNull();

        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',

            // Associations - validate with tenant_id and school_id
            'subject_id' => [
                'sometimes',
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
                'sometimes',
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
                'sometimes',
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($tenantId, $schoolId) {
                    if ($tenantId && $schoolId) {
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
                'sometimes',
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($tenantId, $schoolId) {
                    if ($tenantId && $schoolId) {
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
            'period' => 'sometimes|required|in:morning,afternoon,evening,night',
            'day_of_week' => 'sometimes|required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i|after:start_time',

            // Date range
            'start_date' => 'sometimes|required|date|before:end_date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'recurrence_pattern' => 'nullable|array',

            // Configuration
            'status' => 'sometimes|in:active,suspended,cancelled,completed',
            'is_online' => 'boolean',
            'online_meeting_url' => 'nullable|url|max:500',
            'configuration_json' => 'nullable|array'
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate minimum duration - same as StoreScheduleRequest
            if ($this->filled('start_time') && $this->filled('end_time')) {
                try {
                    // Use strict time parsing to avoid date/timezone side-effects
                    $start = \Carbon\Carbon::createFromFormat('H:i', $this->start_time)->seconds(0);
                    $end = \Carbon\Carbon::createFromFormat('H:i', $this->end_time)->seconds(0);

                    $duration = $start->diffInMinutes($end, false);

                    Log::debug('UpdateScheduleRequest duration validation', [
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
                } catch (\Exception $e) {
                    // If parsing fails, the base rules will already flag format errors
                    Log::warning('Duration validation failed', [
                        'error' => $e->getMessage(),
                        'start_time' => $this->start_time,
                        'end_time' => $this->end_time
                    ]);
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
