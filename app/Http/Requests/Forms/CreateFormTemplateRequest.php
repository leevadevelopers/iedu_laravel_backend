<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class CreateFormTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // return auth()->user()->hasTenantPermission('forms.create_template');
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|string|in:school_registration,school_enrollment,school_setup,student_enrollment,student_registration,student_transfer,attendance,grades,academic_records,behavior_incident,parent_communication,teacher_evaluation,curriculum_planning,extracurricular,field_trip,parent_meeting,student_health,special_education,discipline,graduation,scholarship,staff_management,faculty_recruitment,professional_development,school_calendar,events_management,facilities_management,transportation,cafeteria_management,library_management,technology_management,security_management,maintenance_requests,financial_aid,tuition_management,donation_management,alumni_relations,community_outreach,partnership_management',
            'estimated_completion_time' => 'nullable|string|max:50',
            'is_multi_step' => 'boolean',
            'auto_save' => 'boolean',
            'compliance_level' => 'nullable|string|in:basic,standard,strict,custom',
            'is_default' => 'boolean',
            'metadata' => 'nullable|array',
            'form_configuration' => 'nullable|array',
            'steps' => 'required|array|min:1',
            'steps.*.step_id' => 'required|string',
            'steps.*.step_title' => 'required|string',
            'steps.*.sections' => 'required|array|min:1',
            'steps.*.sections.*.section_id' => 'required|string',
            'steps.*.sections.*.section_title' => 'required|string',
            'steps.*.sections.*.fields' => 'required|array',
            'steps.*.sections.*.fields.*.field_id' => 'required|string',
            'steps.*.sections.*.fields.*.field_type' => 'required|string',
            'steps.*.sections.*.fields.*.label' => 'required|string',
            'form_triggers' => 'nullable|array',
            'validation_rules' => 'nullable|array',
            'workflow_configuration' => 'nullable|array',
            'ai_prompts' => 'nullable|array'
        ];
    }

    public function messages(): array
    {
        return [
            'steps.required' => 'Form template must have at least one step',
            'steps.*.sections.required' => 'Each step must have at least one section',
            'steps.*.sections.*.fields.required' => 'Each section must have at least one field'
        ];
    }
}
