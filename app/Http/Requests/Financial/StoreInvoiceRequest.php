<?php

namespace App\Http\Requests\Financial;

use App\Models\V1\Financial\Fee;
use App\Services\SchoolContextService;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
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
            'billable_id' => 'required|integer',
            'billable_type' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'due_at' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            'items.*.fee_id' => [
                'nullable',
                'exists:fees,id',
                function ($attribute, $value, $fail) use ($tenantId, $schoolId) {
                    if ($value && ($tenantId || $schoolId)) {
                        $fee = Fee::where('id', $value)
                            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                            ->first();

                        if (!$fee) {
                            $fail('The selected fee does not belong to your tenant/school.');
                        }
                    }
                },
            ],
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
