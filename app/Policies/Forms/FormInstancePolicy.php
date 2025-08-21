<?php

namespace App\Policies\Forms;

use App\Models\Forms\FormInstance;
use App\Models\Forms\FormTemplate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FormInstancePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any form instances.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasTenantPermission(['forms.view', 'forms.admin']);
    }

    /**
     * Determine whether the user can view the form instance.
     */
    public function view(User $user, FormInstance $instance): bool
    {
        if (!$user->belongsToTenant($instance->tenant_id)) {
            return false;
        }

        return $user->hasTenantPermission(['forms.view_all', 'forms.admin']) ||
               $instance->user_id === $user->id ||
               $this->canApprove($user, $instance);
    }

    /**
     * Determine whether the user can create form instances.
     */
    public function create(User $user, FormTemplate $template): bool
    {
        if (!$user->belongsToTenant($template->tenant_id)) {
            return false;
        }

        return $user->hasTenantPermission(['forms.create', 'forms.admin']) &&
               $template->is_active;
    }

    /**
     * Determine whether the user can update the form instance.
     */
    public function update(User $user, FormInstance $instance): bool
    {
        if (!$user->belongsToTenant($instance->tenant_id)) {
            return false;
        }

        // Only owner can edit draft forms, or admin can edit any
        return ($instance->user_id === $user->id && $instance->isDraft()) ||
               $user->hasTenantPermission(['forms.edit_all', 'forms.admin']);
    }

    /**
     * Determine whether the user can delete the form instance.
     */
    public function delete(User $user, FormInstance $instance): bool
    {
        if (!$user->belongsToTenant($instance->tenant_id)) {
            return false;
        }

        // Only owner can delete draft forms, or admin can delete any
        return ($instance->user_id === $user->id && $instance->isDraft()) ||
               $user->hasTenantPermission(['forms.delete', 'forms.admin']);
    }

    /**
     * Determine whether the user can perform workflow actions.
     */
    public function workflow(User $user, FormInstance $instance): bool
    {
        if (!$user->belongsToTenant($instance->tenant_id)) {
            return false;
        }

        return $this->canApprove($user, $instance) ||
               $user->hasTenantPermission(['forms.workflow', 'forms.admin']);
    }

    /**
     * Determine whether the user can approve the form instance.
     */
    private function canApprove(User $user, FormInstance $instance): bool
    {
        $template = $instance->template;
        $workflowConfig = $template->workflow_configuration ?? [];
        
        if (empty($workflowConfig)) {
            return false;
        }

        $steps = $workflowConfig['steps'] ?? [];
        $currentState = json_decode($instance->workflow_state ?? '{}', true);
        $currentStep = $currentState['current_step'] ?? null;

        foreach ($steps as $step) {
            if (($step['step_name'] ?? '') === $currentStep) {
                $requiredRole = $step['approver_role'] ?? null;
                if ($requiredRole && $user->hasTenantRole($requiredRole)) {
                    return true;
                }
                
                $requiredPermissions = $step['required_permissions'] ?? [];
                foreach ($requiredPermissions as $permission) {
                    if ($user->hasTenantPermission($permission)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}