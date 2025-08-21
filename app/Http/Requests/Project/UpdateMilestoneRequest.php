<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\Project\MilestoneStatus;

class UpdateMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'target_date' => 'sometimes|required|date',
            'completion_date' => 'nullable|date',
            'status' => 'nullable|string|in:' . implode(',', array_column(MilestoneStatus::cases(), 'value')),
            'weight' => 'nullable|numeric|min:0|max:100',
            'deliverables' => 'nullable|array',
            'success_criteria' => 'nullable|array',
            'responsible_user_id' => 'nullable|exists:users,id',
            'dependencies' => 'nullable|array',
            'notes' => 'nullable|string',
        ];
    }
}
