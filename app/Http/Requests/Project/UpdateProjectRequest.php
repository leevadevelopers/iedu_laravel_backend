<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\Project\ProjectStatus;
use App\Enums\Project\ProjectPriority;
use App\Enums\Project\MethodologyType;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $projectId = $this->route('project') ?? $this->route('id');
        
        return [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|string|max:50|unique:projects,code,' . $projectId,
            'description' => 'sometimes|required|string|min:10',
            'category' => 'nullable|string|max:100',
            'priority' => 'nullable|string|in:' . implode(',', array_column(ProjectPriority::cases(), 'value')),
            'status' => 'nullable|string|in:' . implode(',', array_column(ProjectStatus::cases(), 'value')),
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'budget' => 'sometimes|required|numeric|min:1000|max:99999999.99',
            'currency' => 'nullable|string|size:3',
            'methodology_type' => 'sometimes|required|string|in:' . implode(',', array_column(MethodologyType::cases(), 'value')),
            'metadata' => 'nullable|array',
            'compliance_requirements' => 'nullable|array',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate status transitions
            $this->validateStatusTransition($validator);
        });
    }

    private function validateStatusTransition($validator): void
    {
        if ($this->has('status')) {
            $project = $this->route('project');
            if ($project && !$project->canTransitionTo($this->input('status'))) {
                $validator->errors()->add('status', 'Invalid status transition.');
            }
        }
    }
}
