<?php
namespace App\Models\Forms;

use App\Models\Traits\Tenantable;
use App\Models\Traits\LogsActivityWithTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FormInstance extends Model
{
    use SoftDeletes, Tenantable, LogsActivityWithTenant;

    protected $fillable = [
        'tenant_id', 'form_template_id', 'user_id', 'instance_code', 'form_data',
        'calculated_fields', 'status', 'workflow_state', 'workflow_history',
        'current_step', 'completion_percentage', 'validation_results',
        'compliance_results', 'submitted_at', 'completed_at', 'reference_type',
        'reference_id', 'form_type', 'created_by',
        'public_access_token', 'public_access_enabled', 'public_access_expires_at',
        'submission_type', 'submission_metadata'
    ];

    protected $casts = [
        'form_data' => 'array',
        'calculated_fields' => 'array',
        'workflow_history' => 'array',
        'validation_results' => 'array',
        'compliance_results' => 'array',
        'completion_percentage' => 'float',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'public_access_enabled' => 'boolean',
        'public_access_expires_at' => 'datetime',
        'submission_metadata' => 'array',
    ];

    // Boot method to generate instance code
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->instance_code) {
                $model->instance_code = $model->generateInstanceCode();
            }
        });
    }

    // Relationships
    public function template(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class)->withDefault();
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    // Scopes
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeInProgress($query)
    {
        return $query->whereIn('status', ['draft', 'in_progress']);
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['submitted', 'approved', 'completed']);
    }

    // Helper Methods
    private function generateInstanceCode(): string
    {
        // Get template category safely
        $category = 'FORM';
        if ($this->template) {
            $category = $this->template->category ?? 'FORM';
        }

        $prefix = strtoupper(substr($category, 0, 3));
        $timestamp = now()->format('ymd');
        $random = strtoupper(Str::random(4));

        return $prefix . '-' . $timestamp . '-' . $random;
    }

    public function updateProgress(): void
    {
        $template = $this->template;
        $totalFields = 0;
        $completedFields = 0;

        foreach ($template->steps as $step) {
            foreach ($step['sections'] as $section) {
                foreach ($section['fields'] as $field) {
                    if ($field['required'] ?? false) {
                        $totalFields++;

                        $fieldValue = data_get($this->form_data, $field['field_id']);
                        if (!empty($fieldValue)) {
                            $completedFields++;
                        }
                    }
                }
            }
        }

        $percentage = $totalFields > 0 ? ($completedFields / $totalFields) * 100 : 0;

        $this->update(['completion_percentage' => round($percentage, 2)]);
    }

    public function getFieldValue(string $fieldId, $default = null)
    {
        return data_get($this->form_data, $fieldId, $default);
    }

    public function setFieldValue(string $fieldId, $value): void
    {
        $formData = $this->form_data ?? [];
        data_set($formData, $fieldId, $value);

        $this->update(['form_data' => $formData]);
        $this->updateProgress();
    }

    public function updateFieldValues(array $values): void
    {
        $formData = $this->form_data ?? [];

        foreach ($values as $fieldId => $value) {
            data_set($formData, $fieldId, $value);
        }

        $this->update(['form_data' => $formData]);
        $this->updateProgress();
    }

    public function moveToNextStep(): bool
    {
        $template = $this->template;
        $maxSteps = count($template->steps);

        if ($this->current_step < $maxSteps) {
            $this->increment('current_step');
            return true;
        }

        return false;
    }

    public function moveToPreviousStep(): bool
    {
        if ($this->current_step > 1) {
            $this->decrement('current_step');
            return true;
        }

        return false;
    }

    public function submit(array $submissionData = []): FormSubmission
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return $this->submissions()->create([
            'tenant_id' => $this->tenant_id,
            'submitted_by' => auth()->id() ?? $this->user_id,
            'submission_data' => array_merge($this->form_data, $submissionData),
            'submission_type' => 'submit',
        ]);
    }

    /**
     * Submit form for public access (no authentication required)
     */
    public function submitPublic(array $submissionData = [], array $metadata = []): FormSubmission
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'submission_metadata' => array_merge($this->submission_metadata ?? [], $metadata)
        ]);

        return $this->submissions()->create([
            'tenant_id' => $this->tenant_id,
            'submitted_by' => null, // Public submission
            'submission_data' => array_merge($this->form_data, $submissionData),
            'submission_type' => 'public_submit',
        ]);
    }

    public function approve(int $approvedBy, string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'completed_at' => now(),
        ]);

        // Log approval in workflow history
        $history = $this->workflow_history ?? [];
        $history[] = [
            'action' => 'approved',
            'user_id' => $approvedBy,
            'notes' => $notes,
            'timestamp' => now()->toISOString(),
        ];

        $this->update(['workflow_history' => $history]);
    }

    public function reject(int $rejectedBy, string $reason): void
    {
        $this->update(['status' => 'rejected']);

        // Log rejection in workflow history
        $history = $this->workflow_history ?? [];
        $history[] = [
            'action' => 'rejected',
            'user_id' => $rejectedBy,
            'reason' => $reason,
            'timestamp' => now()->toISOString(),
        ];

        $this->update(['workflow_history' => $history]);
    }

    public function canBeEditedBy(?User $user = null): bool
    {
        // For public forms, check if they can still be edited
        if ($this->submission_type === 'public') {
            return in_array($this->status, ['draft', 'in_progress']);
        }

        // If no user provided, return false for authenticated forms
        if (!$user) {
            return false;
        }

        // Owner can always edit (unless completed)
        if ($this->user_id === $user->id && !in_array($this->status, ['completed', 'approved'])) {
            return true;
        }

        // Check tenant permissions
        return $user->hasTenantPermission(['forms.edit', 'forms.admin']);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return in_array($this->status, ['submitted', 'under_review', 'approved', 'completed']);
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['approved', 'completed']);
    }

    /**
     * Generate a public access token for this instance
     */
    public function generatePublicAccessToken(): string
    {
        $token = Str::random(64);
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

        return config('app.frontend_url') . '/public/form/' . $this->public_access_token;
    }

    /**
     * Scope to find by public access token
     */
    public function scopeByPublicToken($query, string $token)
    {
        return $query->where('public_access_token', $token)
                    ->where('public_access_enabled', true)
                    ->where(function($q) {
                        $q->whereNull('public_access_expires_at')
                          ->orWhere('public_access_expires_at', '>', now());
                    });
    }
}
