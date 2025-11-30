<?php

namespace App\Http\Requests\Documents;

use App\Services\SchoolContextService;
use Illuminate\Foundation\Http\FormRequest;

class GenerateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->getCurrentTenantId();
        $schoolId = $this->getCurrentSchoolId();

        return [
            'template' => 'required|string|in:enrollment_certificate,attendance_declaration,conduct_certificate',
            'student_id' => [
                'required',
                'exists:students,id',
                function ($attribute, $value, $fail) use ($tenantId, $schoolId) {
                    if ($value && ($tenantId || $schoolId)) {
                        $student = \App\Models\V1\SIS\Student\Student::where('id', $value)
                            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                            ->first();

                        if (!$student) {
                            $fail('The selected student does not belong to your tenant/school.');
                        }
                    }
                },
            ],
            'purpose' => 'nullable|string|max:255',
            'signed_by' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    protected function getCurrentTenantId(): ?int
    {
        try {
            $user = auth('api')->user();
            if (!$user) {
                return null;
            }

            if (isset($user->tenant_id) && $user->tenant_id) {
                return $user->tenant_id;
            }

            return session('tenant_id');
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getCurrentSchoolId(): ?int
    {
        try {
            $schoolContextService = app(SchoolContextService::class);
            return $schoolContextService->getCurrentSchoolId();
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function prepareForValidation(): void
    {
        // Remove tenant_id and school_id if provided (set automatically)
        $this->merge(array_filter($this->all(), function ($key) {
            return !in_array($key, ['tenant_id', 'school_id']);
        }, ARRAY_FILTER_USE_KEY));
    }
}

