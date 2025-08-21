<?php 
namespace App\Models\Forms;

use App\Models\Traits\Tenantable;
use App\Models\Traits\LogsActivityWithTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormTemplate extends Model
{
    use SoftDeletes, Tenantable, LogsActivityWithTenant;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'version', 'category', 'methodology_type',
        'estimated_completion_time', 'is_multi_step', 'auto_save', 'compliance_level',
        'is_active', 'is_default', 'metadata', 'form_configuration', 'steps',
        'form_triggers', 'validation_rules', 'workflow_configuration', 'ai_prompts',
        'created_by'
    ];

    protected $casts = [
        'is_multi_step' => 'boolean',
        'auto_save' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'metadata' => 'array',
        'form_configuration' => 'array',
        'steps' => 'array',
        'form_triggers' => 'array',
        'validation_rules' => 'array',
        'workflow_configuration' => 'array',
        'ai_prompts' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(FormInstance::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FormTemplateVersion::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByMethodology($query, string $methodology)
    {
        return $query->where('methodology_type', $methodology);
    }

    public function scopeForTenantAndUser($query, int $tenantId, int $userId)
    {
        return $query->where('tenant_id', $tenantId)
                    ->where(function($q) use ($userId) {
                        $q->where('created_by', $userId)
                          ->orWhere('is_default', true);
                    });
    }

    // Helper Methods
    public function isCompatibleWith(string $methodology): bool
    {
        return $this->methodology_type === 'universal' || 
               $this->methodology_type === $methodology;
    }

    public function getEstimatedDurationInMinutes(): int
    {
        if (!$this->estimated_completion_time) {
            return 30; // Default 30 minutes
        }
        
        // Parse duration string like "45 minutes", "1 hour", "2.5 hours"
        preg_match('/(\d+(?:\.\d+)?)\s*(minute|hour)s?/i', $this->estimated_completion_time, $matches);
        
        if (empty($matches)) {
            return 30;
        }
        
        $value = (float) $matches[1];
        $unit = strtolower($matches[2]);
        
        return $unit === 'hour' ? $value * 60 : $value;
    }

    public function getFieldById(string $fieldId): ?array
    {
        foreach ($this->steps as $step) {
            foreach ($step['sections'] as $section) {
                foreach ($section['fields'] as $field) {
                    if ($field['field_id'] === $fieldId) {
                        return $field;
                    }
                }
            }
        }
        return null;
    }

    public function getAllFields(): array
    {
        $fields = [];
        foreach ($this->steps as $step) {
            foreach ($step['sections'] as $section) {
                foreach ($section['fields'] as $field) {
                    $fields[$field['field_id']] = $field;
                }
            }
        }
        return $fields;
    }

    public function createVersion(string $changesSummary, int $createdBy): FormTemplateVersion
    {
        $newVersionNumber = $this->getNextVersionNumber();
        
        return $this->versions()->create([
            'version_number' => $newVersionNumber,
            'changes_summary' => $changesSummary,
            'template_data' => $this->toArray(),
            'created_by' => $createdBy,
        ]);
    }

    private function getNextVersionNumber(): string
    {
        $latestVersion = $this->versions()->latest()->first();
        
        if (!$latestVersion) {
            return '1.1';
        }
        
        $parts = explode('.', $latestVersion->version_number);
        $minor = (int) ($parts[1] ?? 0) + 1;
        
        return $parts[0] . '.' . $minor;
    }

    public function duplicate(array $changes = []): self
    {
        $templateData = $this->toArray();
        unset($templateData['id'], $templateData['created_at'], $templateData['updated_at']);
        
        $templateData = array_merge($templateData, $changes);
        $templateData['name'] = $changes['name'] ?? $this->name . ' (Copy)';
        $templateData['is_default'] = false;
        $templateData['version'] = '1.0';
        
        return static::create($templateData);
    }
}
