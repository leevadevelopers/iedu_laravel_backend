<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        // Get the current tenant from the user making the request
        $currentUser = $request->user();
        $tenantId = $currentUser ? $currentUser->getCurrentTenant()?->id : null;
        
        // Get tenant pivot data for the current tenant
        $currentTenant = null;
        $pivotData = null;
        if ($tenantId && $this->tenants) {
            $currentTenant = $this->tenants->firstWhere('id', $tenantId);
            if ($currentTenant) {
                $pivotData = $currentTenant->pivot;
            }
        }
        
        // Get role information from pivot
        // Note: role_id can be either a string (like 'admin', 'teacher') or an integer ID
        $roleId = $pivotData->role_id ?? null;
        $roleName = null;
        
        if ($roleId) {
            // Try to get role from Spatie Permission roles table
            try {
                // Use DB facade to query roles table directly
                $role = \Illuminate\Support\Facades\DB::table('roles')
                    ->where('id', $roleId)
                    ->first();
                
                if ($role) {
                    // Use display_name if available, otherwise use name
                    $roleName = $role->display_name ?? $role->name ?? null;
                }
            } catch (\Exception $e) {
                // If roles table doesn't exist or query fails, fall back to mapping
            }
            
            // If we still don't have a role name, try string mapping
            if (!$roleName) {
                $roleMap = [
                    'admin' => 'Administrator',
                    'teacher' => 'Teacher',
                    'student' => 'Student',
                    'parent' => 'Parent',
                    'staff' => 'Staff',
                ];
                
                // If role_id is a string key, use the map
                if (is_string($roleId) && isset($roleMap[$roleId])) {
                    $roleName = $roleMap[$roleId];
                } elseif (is_string($roleId)) {
                    // If it's a string but not in map, capitalize it
                    $roleName = ucfirst($roleId);
                } elseif (is_numeric($roleId)) {
                    // If it's a number and we couldn't find in DB, use a generic label
                    $roleName = null; // Frontend will handle mapping
                }
            }
        }
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'identifier' => $this->identifier,
            'email' => $this->type === 'email' ? $this->identifier : null, // Map email from identifier
            'type' => $this->type,
            'phone' => $this->phone,
            'company' => $this->company,
            'job_title' => $this->job_title,
            'bio' => $this->bio,
            'profile_photo_path' => $this->profile_photo_path,
            'is_active' => $this->is_active,
            'verified_at' => $this->verified_at?->toISOString(),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Status from tenant pivot or default to active
            'status' => $pivotData->status ?? 'active',
            
            // Role information - simplified for frontend
            'role' => $roleName,
            'role_id' => $roleId,
            
            'custom_permissions' => $this->when(isset($this->custom_permissions), $this->custom_permissions),
            'tenant_status' => $pivotData->status ?? null,
            'joined_at' => $pivotData->joined_at ?? null,
            
            'current_tenant_context' => $this->when($request->user()?->id === $this->id, function () {
                return $this->getTenantContext();
            }),
        ];
    }
}