<?php

namespace App\Models;

use App\Traits\HasOrganization;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormInstance extends BaseModel
{
    use HasFactory, SoftDeletes, HasOrganization, HasAuditTrail;

    protected $fillable = [
        'form_template_id',
        'instance_name',
        'reference_number',
        'data_json',
        'metadata_json',
        'submitted_by',
        'submission_date',
        'status',
        'workflow_state',
        'workflow_history_json',
        'validation_status',
        'validation_errors_json',
        'compliance_check_json',
        'ai_suggestions_json',
        'ai_validation_score',
        'ai_insights_json',
        'related_entity_type',
        'related_entity_id',
        'version',
        'parent_instance_id',
        'completed_at'
    ];

    protected $casts = [
        'data_json' => 'array',
        'metadata_json' => 'array',
        'submission_date' => 'datetime',
        'workflow_history_json' => 'array',
        'validation_errors_json' => 'array',
        'compliance_check_json' => 'array',
        'ai_suggestions_json' => 'array',
        'ai_validation_score' => 'decimal:2',
        'ai_insights_json' => 'array',
        'version' => 'integer',
        'completed_at' => 'datetime'
    ];

    /**
     * Get the form template.
     */
    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class);
    }

    /**
     * Get the user who submitted the form.
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
