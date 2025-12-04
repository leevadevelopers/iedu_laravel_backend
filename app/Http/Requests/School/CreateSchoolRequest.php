<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class CreateSchoolRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Add your authorization logic here
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert empty strings to null for date fields
        if ($this->has('established_date') && $this->established_date === '') {
            $this->merge(['established_date' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // tenant_id is optional - Tenantable trait will auto-set it from authenticated user
            'tenant_id' => 'nullable|integer|exists:tenants,id',
            'official_name' => 'required|string|max:255',
            'display_name' => 'required|string|max:255',
            'short_name' => 'required|string|max:50',
            'school_code' => 'required|string|max:50|unique:schools,school_code',
            // School types - Contexto Moçambicano
            'school_type' => 'required|in:pre_primary,primary,secondary_general,technical_professional,institute_medio,higher_education,teacher_training,adult_education,special_needs',
            // Educational levels and grades can be added in subsequent steps
            'educational_levels' => 'nullable|array',
            'grade_range_min' => 'nullable|string|max:10',
            'grade_range_max' => 'nullable|string|max:10',
            // Contact info can be added in subsequent steps
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'address_json' => 'nullable|array',
            'country_code' => 'required|string|size:2',
            'state_province' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:50',
            'ministry_education_code' => 'nullable|string|max:100',
            'accreditation_status' => 'nullable|in:accredited,candidate,probation,not_accredited',
            'academic_calendar_type' => 'nullable|in:semester,trimester,quarter,year_round,custom',
            'academic_year_start_month' => 'nullable|integer|min:1|max:12',
            'grading_system' => 'nullable|in:traditional_letter,percentage,points,standards_based,narrative,mixed',
            'attendance_tracking_level' => 'nullable|in:daily,period,subject,flexible',
            'educational_philosophy' => 'nullable|string',
            'language_instruction' => 'nullable|array',
            'religious_affiliation' => 'nullable|string|max:100',
            'student_capacity' => 'nullable|integer|min:1',
            'established_date' => 'nullable|date',
            'subscription_plan' => 'nullable|in:basic,standard,premium,enterprise,custom',
            'feature_flags' => 'nullable|array',
            'integration_settings' => 'nullable|array',
            'branding_configuration' => 'nullable|array',
            'form_data' => 'nullable|array', // For Form Engine integration
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tenant_id.exists' => 'The selected tenant does not exist.',
            'official_name.required' => 'Official name is required.',
            'display_name.required' => 'Display name is required.',
            'short_name.required' => 'Short name is required.',
            'school_code.required' => 'School code is required.',
            'school_code.unique' => 'School code must be unique.',
            'school_type.required' => 'School type is required.',
            'school_type.in' => 'Invalid school type selected.',
            'email.email' => 'Please provide a valid email address.',
            'country_code.required' => 'Country code is required.',
            'country_code.size' => 'Country code must be exactly 2 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'tenant_id' => 'tenant',
            'official_name' => 'official name',
            'display_name' => 'display name',
            'short_name' => 'short name',
            'school_code' => 'school code',
            'school_type' => 'school type',
            'educational_levels' => 'educational levels',
            'grade_range_min' => 'minimum grade range',
            'grade_range_max' => 'maximum grade range',
            'address_json' => 'address',
            'country_code' => 'country code',
            'state_province' => 'state/province',
            'ministry_education_code' => 'ministry education code',
            'accreditation_status' => 'accreditation status',
            'academic_calendar_type' => 'academic calendar type',
            'academic_year_start_month' => 'academic year start month',
            'grading_system' => 'grading system',
            'attendance_tracking_level' => 'attendance tracking level',
            'educational_philosophy' => 'educational philosophy',
            'language_instruction' => 'language instruction',
            'religious_affiliation' => 'religious affiliation',
            'student_capacity' => 'student capacity',
            'established_date' => 'established date',
            'subscription_plan' => 'subscription plan',
            'feature_flags' => 'feature flags',
            'integration_settings' => 'integration settings',
            'branding_configuration' => 'branding configuration',
            'form_data' => 'form data',
        ];
    }
}
