<?php

namespace App\Http\Requests\Financial;

use App\Models\V1\Financial\FinancialAccount;
use App\Services\SchoolContextService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
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
            'account_id' => [
                'sometimes',
                'required',
                'exists:financial_accounts,id',
                function ($attribute, $value, $fail) use ($tenantId, $schoolId) {
                    if ($value && ($tenantId || $schoolId)) {
                        $account = FinancialAccount::where('id', $value)
                            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                            ->first();

                        if (!$account) {
                            $fail('The selected account does not belong to your tenant/school.');
                        }
                    }
                },
            ],
            'category' => 'sometimes|required|string|max:100',
            'amount' => 'sometimes|required|numeric|min:0.01',
            'description' => 'sometimes|required|string',
            'incurred_at' => 'sometimes|required|date',
            'receipt_path' => 'nullable|string',
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
        // Remover tenant_id e school_id se forem enviados (são definidos automaticamente)
        $this->merge(array_filter($this->all(), function ($key) {
            return !in_array($key, ['tenant_id', 'school_id']);
        }, ARRAY_FILTER_USE_KEY));
    }
}
