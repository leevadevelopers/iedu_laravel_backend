<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\Project\ProjectStatus;
use App\Enums\Project\ProjectPriority;
use App\Enums\Project\MethodologyType;

class CreateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handle authorization in middleware/policies
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:projects,code',
            'description' => 'required|string|min:10',
            'category' => 'nullable|string|max:100',
            'priority' => 'nullable|string|in:' . implode(',', array_column(ProjectPriority::cases(), 'value')),
            'status' => 'nullable|string|in:' . implode(',', array_column(ProjectStatus::cases(), 'value')),
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'budget' => 'required|numeric|min:1000|max:99999999.99',
            'currency' => 'nullable|string|size:3',
            'methodology_type' => 'required|string|in:' . implode(',', array_column(MethodologyType::cases(), 'value')),
            'metadata' => 'nullable|array',
            'compliance_requirements' => 'nullable|array',
            
            // Form instance integration
            'form_instance_id' => 'nullable|exists:form_instances,id',
            'form_data' => 'nullable|array',
            
            // Milestones
            'milestones' => 'nullable|array',
            'milestones.*.name' => 'required|string|max:255',
            'milestones.*.description' => 'nullable|string',
            'milestones.*.target_date' => 'required|date|after_or_equal:start_date|before_or_equal:end_date',
            'milestones.*.weight' => 'nullable|numeric|min:0|max:100',
            'milestones.*.responsible_user_id' => 'nullable|exists:users,id',
            
            // Stakeholders
            'stakeholders' => 'nullable|array',
            'stakeholders.*.name' => 'required|string|max:255',
            'stakeholders.*.email' => 'nullable|email',
            'stakeholders.*.organization' => 'nullable|string|max:255',
            'stakeholders.*.role' => 'required|string|max:255',
            'stakeholders.*.influence_level' => 'nullable|integer|min:1|max:5',
            'stakeholders.*.interest_level' => 'nullable|integer|min:1|max:5',
            
            // Team members
            'team_members' => 'nullable|array',
            'team_members.*' => 'exists:users,id',
            
            // Project-specific methodology requirements
            'environmental_screening' => 'nullable|boolean',
            'gender_integration' => 'nullable|boolean',
            'marking_branding' => 'nullable|boolean',
            'safeguards_screening' => 'nullable|boolean',
            'results_framework' => 'nullable|boolean',
            'logical_framework' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Project name is required.',
            'description.min' => 'Project description must be at least 10 characters.',
            'start_date.after_or_equal' => 'Project start date cannot be in the past.',
            'end_date.after' => 'Project end date must be after the start date.',
            'budget.min' => 'Project budget must be at least $1,000.',
            'budget.max' => 'Project budget cannot exceed $99,999,999.99.',
            'methodology_type.required' => 'Please select a project methodology.',
            'milestones.*.target_date.after_or_equal' => 'Milestone dates must be within the project timeline.',
            'milestones.*.target_date.before_or_equal' => 'Milestone dates must be within the project timeline.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate milestone weights total to 100% if provided
            if ($this->has('milestones')) {
                $totalWeight = collect($this->input('milestones', []))
                    ->sum('weight');
                
                if ($totalWeight > 0 && abs($totalWeight - 100) > 0.01) {
                    $validator->errors()->add('milestones', 'Milestone weights must total 100%.');
                }
            }
            
            // Validate methodology-specific requirements
            $this->validateMethodologyRequirements($validator);
        });
    }

    private function validateMethodologyRequirements($validator): void
    {
        $methodology = $this->input('methodology_type');
        
        switch ($methodology) {
            case 'usaid':
                if ($this->input('budget', 0) > 100000) {
                    if (!$this->input('environmental_screening')) {
                        $validator->errors()->add('environmental_screening', 'Environmental screening is required for USAID projects over $100,000.');
                    }
                    if (!$this->input('gender_integration')) {
                        $validator->errors()->add('gender_integration', 'Gender integration is required for USAID projects.');
                    }
                }
                break;
                
            case 'world_bank':
                if (!$this->input('safeguards_screening')) {
                    $validator->errors()->add('safeguards_screening', 'Safeguards screening is required for World Bank projects.');
                }
                if (!$this->input('results_framework')) {
                    $validator->errors()->add('results_framework', 'Results framework is required for World Bank projects.');
                }
                break;
                
            case 'eu':
                if (!$this->input('logical_framework')) {
                    $validator->errors()->add('logical_framework', 'Logical framework is required for EU projects.');
                }
                break;
        }
    }
}
