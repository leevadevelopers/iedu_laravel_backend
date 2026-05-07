<?php

namespace App\Services\Auth;

use App\Models\Settings\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SchoolRegistrationService
{
    /**
     * @return array{user: User, tenant: Tenant}
     */
    public function provisionSchoolOwner(array $validatedData, string $organizationName, Role $schoolOwnerRole): array
    {
        return DB::transaction(function () use ($validatedData, $organizationName, $schoolOwnerRole) {
            $user = User::create([
                'name' => $validatedData['name'],
                'identifier' => $validatedData['identifier'],
                'type' => $validatedData['type'],
                'password' => Hash::make($validatedData['password']),
                'role_id' => $schoolOwnerRole->id,
                'tenant_id' => null,
                'user_type' => 'admin',
            ]);

            if (config('iedu.testing_abort_after_user_create')) {
                throw new \RuntimeException('Testing: abort after user create');
            }

            $tenant = Tenant::create([
                'name' => $organizationName,
                'slug' => $this->buildUniqueTenantSlug($organizationName),
                'owner_id' => $user->id,
                'is_active' => true,
                'settings' => [
                    'timezone' => $validatedData['timezone'] ?? 'Africa/Maputo',
                    'currency' => 'MZN',
                    'language' => $validatedData['locale'] ?? 'pt-MZ',
                    'country_code' => strtoupper($validatedData['country_code'] ?? 'MZ'),
                    'features' => [],
                ],
                'created_by' => $user->id,
            ]);

            $user->tenants()->attach($tenant->id, [
                'role_id' => $schoolOwnerRole->id,
                'permissions' => json_encode([
                    'granted' => [],
                    'denied' => [],
                ]),
                'current_tenant' => true,
                'joined_at' => now(),
                'status' => 'active',
            ]);

            $user->update(['tenant_id' => $tenant->id]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
            $user->assignRole($schoolOwnerRole);
            app(PermissionRegistrar::class)->setPermissionsTeamId(null);

            return [
                'user' => $user,
                'tenant' => $tenant,
            ];
        });
    }

    private function buildUniqueTenantSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
