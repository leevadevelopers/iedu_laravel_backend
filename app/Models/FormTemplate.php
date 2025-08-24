<?php

namespace App\Models;

use App\Traits\HasOrganization;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormTemplate extends BaseModel
{
    use HasFactory, SoftDeletes, HasOrganization, HasAuditTrail;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'category',
        'compliance_level',
        'version',
        'is_active',
        'is_default',
        'is_system_template',
        'structure_json',
        'validation_rules_json',
        'conditional_logic_json',
        'ai_prompts_json',
        'workflow_enabled',
        'approval_chain_json',
        'notification_rules_json',
        'escalation_rules_json',
        'estimated_completion_time',
        'required_permissions_json',
        'tags_json',
        'status',
        'parent_template_id',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'is_system_template' => 'boolean',
        'structure_json' => 'array',
        'validation_rules_json' => 'array',
        'conditional_logic_json' => 'array',
        'ai_prompts_json' => 'array',
        'workflow_enabled' => 'boolean',
        'approval_chain_json' => 'array',
        'notification_rules_json' => 'array',
        'escalation_rules_json' => 'array',
        'estimated_completion_time' => 'integer',
        'required_permissions_json' => 'array',
        'tags_json' => 'array'
    ];

    /**
     * Get the form instances for this template.
     */
    public function formInstances(): HasMany
    {
        return $this->hasMany(\App\Models\Forms\FormInstance::class);
    }
}
