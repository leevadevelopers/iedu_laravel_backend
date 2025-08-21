<?php 
namespace App\Rules\Forms;

use Illuminate\Contracts\Validation\Rule;

class FormFieldValidation implements Rule
{
    protected $fieldType;
    protected $fieldConfig;

    public function __construct(string $fieldType, array $fieldConfig = [])
    {
        $this->fieldType = $fieldType;
        $this->fieldConfig = $fieldConfig;
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value): bool
    {
        if ($value === null || $value === '') {
            return true; // Required validation is handled separately
        }

        return match($this->fieldType) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'number' => is_numeric($value) && $this->validateNumericRange($value),
            'currency' => is_numeric($value) && $value >= 0,
            'date' => $this->validateDate($value),
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'phone' => $this->validatePhone($value),
            'file_upload' => $this->validateFile($value),
            'json' => $this->validateJson($value),
            default => true
        };
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return match($this->fieldType) {
            'email' => 'The :attribute must be a valid email address.',
            'number' => 'The :attribute must be a valid number within the allowed range.',
            'currency' => 'The :attribute must be a valid currency amount.',
            'date' => 'The :attribute must be a valid date.',
            'url' => 'The :attribute must be a valid URL.',
            'phone' => 'The :attribute must be a valid phone number.',
            'file_upload' => 'The :attribute must be a valid file.',
            'json' => 'The :attribute must be valid JSON.',
            default => 'The :attribute is invalid.'
        };
    }

    private function validateNumericRange($value): bool
    {
        $min = $this->fieldConfig['min'] ?? null;
        $max = $this->fieldConfig['max'] ?? null;
        
        if ($min !== null && $value < $min) {
            return false;
        }
        
        if ($max !== null && $value > $max) {
            return false;
        }
        
        return true;
    }

    private function validateDate($value): bool
    {
        try {
            $date = new \DateTime($value);
            
            $minDate = $this->fieldConfig['min_date'] ?? null;
            $maxDate = $this->fieldConfig['max_date'] ?? null;
            
            if ($minDate && $date < new \DateTime($minDate)) {
                return false;
            }
            
            if ($maxDate && $date > new \DateTime($maxDate)) {
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function validatePhone($value): bool
    {
        // Basic phone number validation
        $pattern = '/^[\+]?[1-9][\d]{0,15}$/';
        return preg_match($pattern, preg_replace('/[\s\-\(\)]/', '', $value));
    }

    private function validateFile($value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        
        $allowedTypes = $this->fieldConfig['allowed_types'] ?? [];
        $maxSize = $this->parseSize($this->fieldConfig['max_size'] ?? '10MB');
        
        if (isset($value['size']) && $value['size'] > $maxSize) {
            return false;
        }
        
        if (!empty($allowedTypes)) {
            $extension = pathinfo($value['name'] ?? '', PATHINFO_EXTENSION);
            $mimeType = $value['type'] ?? '';
            
            if (!in_array($extension, $allowedTypes) && !in_array($mimeType, $allowedTypes)) {
                return false;
            }
        }
        
        return true;
    }

    private function validateJson($value): bool
    {
        if (is_array($value) || is_object($value)) {
            return true;
        }
        
        if (is_string($value)) {
            json_decode($value);
            return json_last_error() === JSON_ERROR_NONE;
        }
        
        return false;
    }

    private function parseSize(string $size): int
    {
        $units = ['B' => 1, 'KB' => 1024, 'MB' => 1048576, 'GB' => 1073741824];
        
        if (preg_match('/(\d+)\s*([A-Z]{1,2})/i', $size, $matches)) {
            $value = (int) $matches[1];
            $unit = strtoupper($matches[2]);
            return $value * ($units[$unit] ?? 1);
        }
        
        return (int) $size;
    }
}