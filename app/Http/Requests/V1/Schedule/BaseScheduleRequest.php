<?php

namespace App\Http\Requests\V1\Schedule;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && $this->hasValidSchoolAssociation();
    }

    protected function hasValidSchoolAssociation(): bool
    {
        try {
            $user = auth()->user();
            return $user && $user->activeSchools()->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function getCurrentSchoolId(): int
    {
        $user = auth()->user();

        if (!$user) {
            throw new \Exception('User not authenticated');
        }

        $school = $user->activeSchools()->first();

        if (!$school) {
            throw new \Exception('User is not associated with any schools');
        }

        return $school->school_id;
    }

    /**
     * Get the current tenant ID safely, returning null if not available
     */
    protected function getCurrentTenantIdOrNull(): ?int
    {
        try {
            $user = auth()->user();
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
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the current school ID safely, returning null if not available
     */
    protected function getCurrentSchoolIdOrNull(): ?int
    {
        try {
            return $this->getCurrentSchoolId();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'string' => 'O campo :attribute deve ser um texto.',
            'integer' => 'O campo :attribute deve ser um número inteiro.',
            'numeric' => 'O campo :attribute deve ser um número.',
            'date' => 'O campo :attribute deve ser uma data válida.',
            'date_format' => 'O campo :attribute deve ter o formato :format.',
            'email' => 'O campo :attribute deve ser um email válido.',
            'unique' => 'Este :attribute já está em uso.',
            'exists' => 'O :attribute selecionado é inválido.',
            'in' => 'O :attribute selecionado é inválido.',
            'min' => 'O campo :attribute deve ter pelo menos :min caracteres.',
            'max' => 'O campo :attribute não pode ter mais que :max caracteres.',
            'between' => 'O campo :attribute deve ter entre :min e :max.',
            'array' => 'O campo :attribute deve ser um array.',
            'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
            'after' => 'O campo :attribute deve ser posterior a :date.',
            'before' => 'O campo :attribute deve ser anterior a :date.',
        ];
    }

    public function attributes(): array
    {
        return [
            'school_id' => 'escola',
            'academic_year_id' => 'ano letivo',
            'academic_term_id' => 'período letivo',
            'subject_id' => 'disciplina',
            'class_id' => 'turma',
            'teacher_id' => 'professor',
            'classroom' => 'sala de aula',
            'day_of_week' => 'dia da semana',
            'start_time' => 'horário de início',
            'end_time' => 'horário de término',
            'start_date' => 'data de início',
            'end_date' => 'data de término',
            'lesson_date' => 'data da aula',
            'duration_minutes' => 'duração em minutos',
            'is_online' => 'modalidade online',
            'online_meeting_url' => 'URL da reunião online',
            'content_type' => 'tipo de conteúdo',
            'student_id' => 'estudante',
        ];
    }
}
