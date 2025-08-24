<?php

// =====================================================
// FORM VALIDATION SERVICES
// =====================================================

// File: app/Services/Forms/SmartFormValidator.php
namespace App\Services\Forms;

use App\Models\Forms\FormTemplate;
use App\Models\Projects\Project;
use App\Services\Forms\Compliance\DefaultComplianceChecker;
use App\Services\Forms\Compliance\EUComplianceChecker;
use App\Services\Forms\Compliance\USAIDComplianceChecker;
use App\Services\Forms\Compliance\WorldBankComplianceChecker;
use App\Services\Forms\Compliance\ComplianceCheckerInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SmartFormValidator
{
    protected $template;
    protected $ruleEngine;

    public function __construct(FormTemplate $template, FormRuleEngine $ruleEngine)
    {
        $this->template = $template;
        $this->ruleEngine = $ruleEngine;
    }

    /**
     * Validate form data against template rules
     */
    public function validateRules(array $formData): array
    {
        $errors = [];
        $warnings = [];

        try {
            // Validate each field according to template definition
            foreach ($this->template->getAllFields() as $fieldId => $field) {
                $fieldErrors = $this->validateField($fieldId, $field, $formData);
                if (!empty($fieldErrors)) {
                    $errors[$fieldId] = $fieldErrors;
                }
            }

            // Validate custom template rules
            $customErrors = $this->validateCustomRules($formData);
            $errors = array_merge($errors, $customErrors);

        } catch (\Exception $e) {
            Log::error('Form validation failed', [
                'template_id' => $this->template->id,
                'error' => $e->getMessage()
            ]);
            $errors['system'] = ['Validation system error'];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Validate business logic rules
     */
    public function validateBusinessLogic(array $formData): array
    {
        $errors = [];
        $warnings = [];

        // Budget-team alignment validation
        if (isset($formData['budget']) && isset($formData['team_size'])) {
            $budget = (float) $formData['budget'];
            $teamSize = (int) $formData['team_size'];

            if ($budget > 500000 && $teamSize < 5) {
                $warnings['team_size'] = ['Large budget projects should have at least 5 team members'];
            }

            if ($budget > 1000000 && $teamSize < 8) {
                $errors['team_size'] = ['Projects over $1M must have at least 8 team members'];
            }
        }

        // Date logic validation
        if (isset($formData['start_date']) && isset($formData['end_date'])) {
            $startDate = new \DateTime($formData['start_date']);
            $endDate = new \DateTime($formData['end_date']);

            if ($endDate <= $startDate) {
                $errors['end_date'] = ['End date must be after start date'];
            }

            $duration = $startDate->diff($endDate);
            if ($duration->days > 1825) { // 5 years
                $warnings['end_date'] = ['Project duration exceeds 5 years'];
            }
        }

        // Risk-budget alignment
        if (isset($formData['risk_level']) && isset($formData['budget'])) {
            $riskLevel = $formData['risk_level'];
            $budget = (float) $formData['budget'];

            if ($riskLevel === 'high' && $budget > 2000000) {
                $warnings['risk_level'] = ['High-risk projects over $2M require additional oversight'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Validate cross-field dependencies
     */
    public function validateCrossFields(array $formData): array
    {
        $errors = [];
        $warnings = [];

        $validationRules = $this->template->validation_rules ?? [];

        foreach ($validationRules as $rule) {
            if (($rule['rule_type'] ?? '') === 'cross_field') {
                $result = $this->validateCrossFieldRule($rule, $formData);
                if (!$result['valid']) {
                    if ($result['severity'] === 'error') {
                        $errors[$rule['field']] = $result['messages'];
                    } else {
                        $warnings[$rule['field']] = $result['messages'];
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    private function validateField(string $fieldId, array $field, array $formData): array
    {
        $errors = [];
        $value = $formData[$fieldId] ?? null;
        $fieldType = $field['field_type'] ?? 'text';
        $required = $field['required'] ?? false;
        $validation = $field['validation'] ?? [];

        // Required field validation
        if ($required && ($value === null || $value === '')) {
            $errors[] = ($field['label'] ?? $fieldId) . ' is required';
            return $errors; // Skip further validation if required field is empty
        }

        // Skip validation if field is empty and not required
        if ($value === null || $value === '') {
            return $errors;
        }

        // Type-specific validation
        switch ($fieldType) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Invalid email format';
                }
                break;

            case 'number':
            case 'currency':
                if (!is_numeric($value)) {
                    $errors[] = 'Must be a valid number';
                } else {
                    $numValue = (float) $value;
                    if (isset($validation['min']) && $numValue < $validation['min']) {
                        $errors[] = "Must be at least {$validation['min']}";
                    }
                    if (isset($validation['max']) && $numValue > $validation['max']) {
                        $errors[] = "Must not exceed {$validation['max']}";
                    }
                }
                break;

            case 'date':
                try {
                    $date = new \DateTime($value);
                    if (isset($validation['min_date'])) {
                        $minDate = new \DateTime($validation['min_date']);
                        if ($date < $minDate) {
                            $errors[] = "Date must be after {$validation['min_date']}";
                        }
                    }
                    if (isset($validation['max_date'])) {
                        $maxDate = new \DateTime($validation['max_date']);
                        if ($date > $maxDate) {
                            $errors[] = "Date must be before {$validation['max_date']}";
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = 'Invalid date format';
                }
                break;

            case 'text':
            case 'textarea':
                $length = strlen($value);
                if (isset($validation['min_length']) && $length < $validation['min_length']) {
                    $errors[] = "Must be at least {$validation['min_length']} characters";
                }
                if (isset($validation['max_length']) && $length > $validation['max_length']) {
                    $errors[] = "Must not exceed {$validation['max_length']} characters";
                }
                if (isset($validation['pattern'])) {
                    if (!preg_match($validation['pattern'], $value)) {
                        $errors[] = $validation['pattern_message'] ?? 'Invalid format';
                    }
                }
                break;

            case 'file_upload':
                if (is_array($value)) {
                    // Handle multiple files
                    foreach ($value as $file) {
                        $fileErrors = $this->validateFile($file, $validation);
                        $errors = array_merge($errors, $fileErrors);
                    }
                } elseif (is_object($value) && isset($value['uploadedFile'])) {
                    // Handle single file with uploaded file data
                    $fileErrors = $this->validateUploadedFile($value, $validation);
                    $errors = array_merge($errors, $fileErrors);
                } elseif (is_array($value) && isset($value['uploadedFile'])) {
                    // Handle single file with uploaded file data (array format)
                    $fileErrors = $this->validateUploadedFile($value, $validation);
                    $errors = array_merge($errors, $fileErrors);
                }
                break;
        }

        // Custom validation rules
        if (isset($validation['custom_rules'])) {
            foreach ($validation['custom_rules'] as $rule) {
                $customErrors = $this->validateCustomRule($rule, $value, $formData);
                $errors = array_merge($errors, $customErrors);
            }
        }

        return $errors;
    }

    private function validateFile(array $file, array $validation): array
    {
        $errors = [];

        // File size validation
        if (isset($validation['max_size'])) {
            $maxSize = $this->parseFileSize($validation['max_size']);
            if ($file['size'] > $maxSize) {
                $errors[] = "File size exceeds {$validation['max_size']}";
            }
        }

        // File type validation
        if (isset($validation['allowed_types'])) {
            $allowedTypes = $validation['allowed_types'];
            $fileType = $file['type'] ?? '';
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);

            if (!in_array($fileType, $allowedTypes) && !in_array($extension, $allowedTypes)) {
                $errors[] = 'File type not allowed';
            }
        }

        return $errors;
    }

    private function validateUploadedFile($fileData, array $validation): array
    {
        $errors = [];

        // Check if uploadedFile data exists
        if (!isset($fileData['uploadedFile']) || !is_array($fileData['uploadedFile'])) {
            $errors[] = 'Invalid file upload data';
            return $errors;
        }

        $uploadedFile = $fileData['uploadedFile'];

        // File size validation
        if (isset($validation['max_size'])) {
            $maxSize = $this->parseFileSize($validation['max_size']);
            if (($uploadedFile['size'] ?? 0) > $maxSize) {
                $errors[] = "File size exceeds {$validation['max_size']}";
            }
        }

        // File type validation
        if (isset($validation['allowed_types'])) {
            $allowedTypes = $validation['allowed_types'];
            $fileType = $uploadedFile['mime_type'] ?? '';
            $extension = $uploadedFile['extension'] ?? '';

            if (!in_array($fileType, $allowedTypes) && !in_array('.' . $extension, $allowedTypes)) {
                $errors[] = 'File type not allowed';
            }
        }

        // Check if file exists on server
        if (isset($uploadedFile['path'])) {
            if (!file_exists(storage_path('app/public/' . $uploadedFile['path']))) {
                $errors[] = 'Uploaded file not found on server';
            }
        }

        return $errors;
    }

    private function parseFileSize(string $size): int
    {
        $units = ['B' => 1, 'KB' => 1024, 'MB' => 1048576, 'GB' => 1073741824];

        if (preg_match('/(\d+)\s*([A-Z]{1,2})/i', $size, $matches)) {
            $value = (int) $matches[1];
            $unit = strtoupper($matches[2]);
            return $value * ($units[$unit] ?? 1);
        }

        return (int) $size;
    }

    private function validateCustomRules(array $formData): array
    {
        $errors = [];
        $validationRules = $this->template->validation_rules ?? [];

        foreach ($validationRules as $rule) {
            if (($rule['rule_type'] ?? '') === 'custom') {
                $result = $this->validateCustomRule($rule, null, $formData);
                if (!empty($result)) {
                    $field = $rule['field'] ?? 'general';
                    $errors[$field] = $result;
                }
            }
        }

        return $errors;
    }

    private function validateCustomRule(array $rule, $value, array $formData): array
    {
        $errors = [];
        $ruleFunction = $rule['validation_function'] ?? '';

        switch ($ruleFunction) {
            // case 'unique_project_name':
            //     if ($this->isProjectNameDuplicate($value)) {
            //         $errors[] = 'Project name already exists';
            //     }
            //     break;

            // case 'budget_team_ratio':
            //     $errors = array_merge($errors, $this->validateBudgetTeamRatio($formData));
            //     break;

            case 'milestone_weights_total':
                $errors = array_merge($errors, $this->validateMilestoneWeights($formData));
                break;

            case 'conditional_required':
                $errors = array_merge($errors, $this->validateConditionalRequired($rule, $formData));
                break;
        }

        return $errors;
    }

    private function validateCrossFieldRule(array $rule, array $formData): array
    {
        $conditions = $rule['conditions'] ?? [];
        $valid = true;
        $messages = [];

        foreach ($conditions as $condition) {
            $result = $this->ruleEngine->evaluateCondition($condition, $formData);
            if (!$result) {
                $valid = false;
                $messages[] = $rule['error_message'] ?? 'Cross-field validation failed';
                break;
            }
        }

        return [
            'valid' => $valid,
            'messages' => $messages,
            'severity' => $rule['severity'] ?? 'error'
        ];
    }

    // private function isProjectNameDuplicate(string $name): bool
    // {
    //     // Check if project name exists in current tenant
    //     return \App\Models\Projects\Project::where('tenant_id', session('tenant_id'))
    //         ->where('name', $name)
    //         ->exists();
    // }

    // private function validateBudgetTeamRatio(array $formData): array
    // {
    //     $errors = [];
    //     $budget = (float) ($formData['budget'] ?? 0);
    //     $teamSize = (int) ($formData['team_size'] ?? 0);

    //     if ($budget > 0 && $teamSize > 0) {
    //         $budgetPerPerson = $budget / $teamSize;

    //         if ($budgetPerPerson > 200000) {
    //             $errors[] = 'Budget per team member seems unusually high';
    //         } elseif ($budgetPerPerson < 10000) {
    //             $errors[] = 'Budget per team member seems unusually low';
    //         }
    //     }

    //     return $errors;
    // }

    private function validateMilestoneWeights(array $formData): array
    {
        $errors = [];
        $milestones = $formData['milestones'] ?? [];

        if (!empty($milestones)) {
            $totalWeight = array_sum(array_column($milestones, 'weight'));

            if (abs($totalWeight - 100) > 0.01) {
                $errors[] = 'Milestone weights must total exactly 100%';
            }
        }

        return $errors;
    }

    private function validateConditionalRequired(array $rule, array $formData): array
    {
        $errors = [];
        $condition = $rule['condition'] ?? '';
        $requiredFields = $rule['required_fields'] ?? [];

        if ($this->ruleEngine->evaluateCondition($condition, $formData)) {
            foreach ($requiredFields as $field) {
                if (empty($formData[$field])) {
                    $errors[] = "Field '{$field}' is required when " . ($rule['condition_description'] ?? 'condition is met');
                }
            }
        }

        return $errors;
    }
}
