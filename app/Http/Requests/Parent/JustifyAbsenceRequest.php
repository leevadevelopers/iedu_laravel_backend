<?php

namespace App\Http\Requests\Parent;

use App\Services\SchoolContextService;
use Illuminate\Foundation\Http\FormRequest;

class JustifyAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = auth('api')->user();

        return [
            'student_id' => [
                'required',
                'exists:students,id',
                function ($attribute, $value, $fail) use ($user) {
                    // Verify parent has access to this student
                    $relationship = \App\Models\V1\SIS\Student\FamilyRelationship::where('guardian_user_id', $user->id)
                        ->where('student_id', $value)
                        ->where('status', 'active')
                        ->first();

                    if (!$relationship) {
                        $fail('You do not have access to this student.');
                    }
                },
            ],
            'date' => 'required|date|before_or_equal:today',
            'reason' => 'required|in:doenca,emergencia,luto,outro',
            'description' => 'required|string|max:1000',
            'attachments' => 'nullable|array',
            'attachments.*' => 'exists:files,id',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Remove tenant_id and school_id if provided (set automatically)
        $this->merge(array_filter($this->all(), function ($key) {
            return !in_array($key, ['tenant_id', 'school_id']);
        }, ARRAY_FILTER_USE_KEY));
    }
}

