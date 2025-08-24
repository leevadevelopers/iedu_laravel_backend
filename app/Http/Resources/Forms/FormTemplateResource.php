<?php

namespace App\Http\Resources\Forms;

namespace App\Http\Resources\Forms;

use Illuminate\Http\Resources\Json\JsonResource;

class FormTemplateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'version' => $this->version,
            'estimated_completion_time' => $this->estimated_completion_time,
            'is_multi_step' => $this->is_multi_step,
            'auto_save' => $this->auto_save,
            'compliance_level' => $this->compliance_level,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'is_system_template' => $this->is_system_template,
            'structure_json' => $this->structure_json,
            'validation_rules_json' => $this->validation_rules_json,
            'conditional_logic_json' => $this->conditional_logic_json,
            'ai_prompts_json' => $this->ai_prompts_json,
            'workflow_enabled' => $this->workflow_enabled,
            'approval_chain_json' => $this->approval_chain_json,
            'notification_rules_json' => $this->notification_rules_json,
            'escalation_rules_json' => $this->escalation_rules_json,
            'required_permissions_json' => $this->required_permissions_json,
            'tags_json' => $this->tags_json,
            'status' => $this->status,
            'parent_template_id' => $this->parent_template_id,
            'created_by' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email
                ];
            }),
            'updated_by' => $this->whenLoaded('updater', function () {
                return [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name,
                    'email' => $this->updater->email
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'versions' => $this->whenLoaded('versions', function () {
                return $this->versions->map(function ($version) {
                    return [
                        'id' => $version->id,
                        'version_number' => $version->version_number,
                        'changes_summary' => $version->changes_summary,
                        'created_by' => $version->creator->name ?? 'Unknown',
                        'created_at' => $version->created_at->toISOString()
                    ];
                });
            }),
            'instances_count' => $this->when(isset($this->instances_count), $this->instances_count),
            'form_configuration' => $this->form_configuration,
            'steps' => $this->steps,
            'form_triggers' => $this->form_triggers,
            'validation_rules' => $this->validation_rules,
            'workflow_configuration' => $this->workflow_configuration,
            'ai_prompts' => $this->ai_prompts,
            'metadata' => $this->metadata,
            'tenant_id' => $this->tenant_id,
            'organization_id' => $this->organization_id
        ];
    }

    private function getTotalFieldCount(): int
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
    private function getMetadataFields(): array
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

