<?php
namespace App\Models\Forms;

use App\Models\Traits\Tenantable;
use App\Models\Traits\LogsActivityWithTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class FormTemplate extends Model
{
    use SoftDeletes, Tenantable, LogsActivityWithTenant;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'version', 'category',
        'estimated_completion_time', 'is_multi_step', 'auto_save', 'compliance_level',
        'is_active', 'is_default', 'metadata', 'form_configuration', 'steps',
        'form_triggers', 'validation_rules', 'workflow_configuration', 'ai_prompts',
        'created_by', 'public_access_token', 'public_access_enabled', 'public_access_expires_at',
        'allow_multiple_submissions', 'max_submissions', 'public_access_settings'
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
        'public_access_enabled' => 'boolean',
        'public_access_expires_at' => 'datetime',
        'allow_multiple_submissions' => 'boolean',
        'public_access_settings' => 'array',
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
        return $this->hasMany(\App\Models\Forms\FormInstance::class);
    }

    /**
     * Compatibility alias for legacy code paths expecting formInstances().
     */
    public function formInstances(): HasMany
    {
        return $this->instances();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FormTemplateVersion::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNull('deleted_at');
    }

    public function scopeNonDeleted($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeOnlyDeleted($query)
    {
        return $query->onlyTrashed();
    }

    public function scopeWithDeleted($query)
    {
        return $query->withTrashed();
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeForTenantAndUser($query, int $tenantId, int $userId)
    {
        return $query->where('tenant_id', $tenantId)
                    ->where(function($q) use ($userId) {
                        $q->where('created_by', $userId)
                          ->orWhere('is_default', true);
                    });
    }

    /**
     * Boot the model and add global scopes
     */
    protected static function boot()
    {
        parent::boot();

        // Always exclude soft-deleted records by default
        static::addGlobalScope('nonDeleted', function ($query) {
            $query->whereNull('deleted_at');
        });
    }

    /**
     * Check if the global scope is working properly
     */
    public static function testGlobalScope()
    {
        $withScope = static::count();
        $withoutScope = static::withoutGlobalScope('nonDeleted')->count();

        return [
            'with_scope' => $withScope,
            'without_scope' => $withoutScope,
            'scope_working' => $withScope < $withoutScope
        ];
    }

    // Helper Methods
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

    // Public Access Methods
    public function generatePublicAccessToken(): string
    {
        $token = \Illuminate\Support\Str::random(64);
        $this->update([
            'public_access_token' => $token,
            'public_access_enabled' => true,
            'public_access_expires_at' => now()->addDays(30) // Token expires in 30 days
        ]);
        return $token;
    }

    /**
     * Revoke public access token
     */
    public function revokePublicAccessToken(): void
    {
        $this->update([
            'public_access_token' => null,
            'public_access_enabled' => false,
            'public_access_expires_at' => null
        ]);
    }

    /**
     * Check if public access is valid
     */
    public function isPublicAccessValid(): bool
    {
        return $this->public_access_enabled &&
               $this->public_access_token &&
               $this->is_active &&
               (!$this->public_access_expires_at || $this->public_access_expires_at->isFuture());
    }

    /**
     * Get public access URL
     */
    public function getPublicAccessUrl(): string
    {
        if (!$this->isPublicAccessValid()) {
            return '';
        }

        return config('app.frontend_url') . '/#/public/form/' . $this->public_access_token;
    }

    /**
     * Scope to find by public access token
     */
    public function scopeByPublicToken($query, string $token)
    {
        return $query->where('public_access_token', $token)
                    ->where('public_access_enabled', true)
                    ->where('is_active', true)
                    ->where(function($q) {
                        $q->whereNull('public_access_expires_at')
                          ->orWhere('public_access_expires_at', '>', now());
                    });
    }

    /**
     * Check if template can accept new submissions
     */
    public function canAcceptSubmissions(): bool
    {
        if (!$this->isPublicAccessValid()) {
            return false;
        }

        // Check submission limits
        if (!$this->allow_multiple_submissions) {
            $existingSubmissions = $this->instances()->where('status', 'submitted')->count();
            return $existingSubmissions === 0;
        }

        if ($this->max_submissions) {
            $existingSubmissions = $this->instances()->where('status', 'submitted')->count();
            return $existingSubmissions < $this->max_submissions;
        }

        return true;
    }

    /**
     * Get submission count
     */
    public function getSubmissionCount(): int
    {
        return $this->instances()->where('status', 'submitted')->count();
    }

    /**
     * Get total field count from all steps and sections
     */
    public function getTotalFieldCount(): int
    {
        $count = 0;
        foreach ($this->steps ?? [] as $step) {
            foreach ($step['sections'] ?? [] as $section) {
                $count += count($section['fields'] ?? []);
            }
        }
        return $count;
    }

    /**
     * Get metadata fields from template configuration
     */
    public function getMetadataFields(): array
    {
        // Default metadata fields if not configured
        $defaultFields = [
            [
                'key' => 'name',
                'label' => 'Name',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'Enter your name'
            ],
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => false,
                'placeholder' => 'Enter your email'
            ],
            [
                'key' => 'phone',
                'label' => 'Phone',
                'type' => 'phone',
                'required' => false,
                'placeholder' => 'Enter your phone number'
            ],
            [
                'key' => 'organization',
                'label' => 'Organization',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'Enter your organization'
            ]
        ];

        // Check if template has custom metadata fields configured
        $publicSettings = $this->public_access_settings ?? [];
        $metadataFields = $publicSettings['metadata_fields'] ?? null;

        if ($metadataFields && is_array($metadataFields)) {
            return $metadataFields;
        }

        return $defaultFields;
    }
}
