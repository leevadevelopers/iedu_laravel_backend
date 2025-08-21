<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\Project\MilestoneStatus;

class CreateMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'target_date' => 'required|date|after_or_equal:today',
            'weight' => 'nullable|numeric|min:0|max:100',
            'deliverables' => 'nullable|array',
            'success_criteria' => 'nullable|array',
            'responsible_user_id' => 'nullable|exists:users,id',
            'dependencies' => 'nullable|array',
            'notes' => 'nullable|string',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate milestone date is within project timeline
            $projectId = $this->route('project');
            if ($projectId) {
                $project = \App\Models\Project\Project::find($projectId);
                if ($project) {
                    $targetDate = $this->input('target_date');
                    if ($targetDate < $project->start_date || $targetDate > $project->end_date) {
                        $validator->errors()->add('target_date', 'Milestone date must be within project timeline.');
                    }
                }
            }
        });
    }
}
