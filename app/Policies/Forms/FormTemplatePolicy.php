<?php

namespace App\Policies\Forms;

use App\Models\Forms\FormTemplate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FormTemplatePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any form templates.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasTenantPermission(['forms.view', 'forms.admin']);
    }

    /**
     * Determine whether the user can view the form template.
     */
    public function view(User $user, FormTemplate $template): bool
    {
        // Users can view templates in their tenant
        if (!$user->belongsToTenant($template->tenant_id)) {
            return false;
        }

        return $user->hasTenantPermission(['forms.view', 'forms.admin']) ||
               $template->created_by === $user->id;
    }

    /**
     * Determine whether the user can create form templates.
     */
    public function create(User $user): bool
    {
        return $user->hasTenantPermission(['forms.create_template', 'forms.admin']);
    }

    /**
     * Determine whether the user can update the form template.
     */
    public function update(User $user, FormTemplate $template): bool
    {
        if (!$user->belongsToTenant($template->tenant_id)) {
            return false;
        }

        return $user->hasTenantPermission(['forms.edit_template', 'forms.admin']) ||
               $template->created_by === $user->id;
    }

    /**
     * Determine whether the user can delete the form template.
     */
    public function delete(User $user, FormTemplate $template): bool
    {
        if (!$user->belongsToTenant($template->tenant_id)) {
            return false;
        }

        // Only admin or creator can delete, and only if no instances exist
        return ($user->hasTenantPermission(['forms.delete_template', 'forms.admin']) ||
                $template->created_by === $user->id) &&
               !$template->instances()->exists();
    }

    /**
     * Determine whether the user can restore the form template.
     */
    public function restore(User $user, FormTemplate $template): bool
    {
        return $this->update($user, $template);
    }

    /**
     * Determine whether the user can permanently delete the form template.
     */
    public function forceDelete(User $user, FormTemplate $template): bool
    {
        return $user->hasTenantPermission('forms.admin');
    }
}