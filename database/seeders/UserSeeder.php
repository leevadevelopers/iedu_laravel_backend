<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Settings\Tenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create tenant ID 1
        $tenant = Tenant::find(1);
        if (!$tenant) {
            $this->command->warn('Tenant ID 1 not found. Please run TenantSeeder first.');
            return;
        }

        // Helper function to get role ID by name
        $getRoleId = function($roleName) {
            $role = DB::table('roles')->where('name', $roleName)->first();
            return $role ? $role->id : null;
        };

        // 1. SUPER ADMIN (Platform-wide, no tenant)
        $superAdmin = User::create([
            'name' => 'Leeva Superadmin',
            'identifier' => 'noreply@leeva.digital',
            'type' => 'email',
            'password' => Hash::make('@Leeva@Ied_U2026'),
            'verified_at' => now(),
            'is_active' => true,
            'profile_photo_path' => 'https://source.unsplash.com/128x128/?face,portrait,person&sig=1',
            'settings' => json_encode(['theme' => 'dark', 'notifications' => true])
        ]);
        $superAdmin->assignRole('super_admin');
        // Super admin doesn't need tenant association

        // 2. SCHOOL OWNER (Assigned to tenant 1)
        $schoolOwner = User::create([
            'name' => 'Dono da Escola',
            'identifier' => 'owner@example.com',
            'type' => 'email',
            'password' => Hash::make('password123'),
            'verified_at' => now(),
            'is_active' => true,
            'tenant_id' => $tenant->id, // Set tenant_id field
            'profile_photo_path' => 'https://source.unsplash.com/128x128/?face,portrait,person&sig=2',
            'settings' => json_encode(['theme' => 'light', 'notifications' => true])
        ]);
        $schoolOwner->assignRole('school_owner');
        // Attach to tenant 1 with current_tenant = true
        $schoolOwner->tenants()->attach($tenant->id, [
            'status' => 'active',
            'role_id' => $getRoleId('school_owner'),
            'current_tenant' => true,
            'joined_at' => now(),
        ]);

        // 3. SCHOOL ADMIN (Assigned to tenant 1)
        $schoolAdmin = User::create([
            'name' => 'Director Administrador',
            'identifier' => 'admin@example.com',
            'type' => 'email',
            'password' => Hash::make('password123'),
            'verified_at' => now(),
            'is_active' => true,
            'tenant_id' => $tenant->id, // Set tenant_id field
            'profile_photo_path' => 'https://source.unsplash.com/128x128/?face,portrait,person&sig=3',
            'settings' => json_encode(['theme' => 'light', 'notifications' => true])
        ]);
        $schoolAdmin->assignRole('school_admin');
        $schoolAdmin->tenants()->attach($tenant->id, [
            'status' => 'active',
            'role_id' => $getRoleId('school_admin'),
            'current_tenant' => false,
            'joined_at' => now(),
        ]);

        // 4. TEACHER (Assigned to tenant 1)
        $teacher = User::create([
            'name' => 'Professor Teste',
            'identifier' => 'teacher@example.com',
            'type' => 'email',
            'password' => Hash::make('password123'),
            'verified_at' => now(),
            'is_active' => true,
            'tenant_id' => $tenant->id, // Set tenant_id field
            'profile_photo_path' => 'https://source.unsplash.com/128x128/?face,portrait,person&sig=4',
            'settings' => json_encode(['theme' => 'light', 'notifications' => true])
        ]);
        $teacher->assignRole('teacher');
        $teacher->tenants()->attach($tenant->id, [
            'status' => 'active',
            'role_id' => $getRoleId('teacher'),
            'current_tenant' => false,
            'joined_at' => now(),
        ]);

        // 5. PARENT (Assigned to tenant 1)
        $parent = User::create([
            'name' => 'Encarregado de Educação',
            'identifier' => 'parent@example.com',
            'type' => 'email',
            'password' => Hash::make('password123'),
            'verified_at' => now(),
            'is_active' => true,
            'tenant_id' => $tenant->id, // Set tenant_id field
            'profile_photo_path' => 'https://source.unsplash.com/128x128/?face,portrait,person&sig=5',
            'settings' => json_encode(['theme' => 'light', 'notifications' => true])
        ]);
        $parent->assignRole('parent');
        $parent->tenants()->attach($tenant->id, [
            'status' => 'active',
            'role_id' => $getRoleId('parent'),
            'current_tenant' => false,
            'joined_at' => now(),
        ]);

        // 6. STUDENT (Assigned to tenant 1)
        $student = User::create([
            'name' => 'Aluno Teste',
            'identifier' => 'student@example.com',
            'type' => 'email',
            'password' => Hash::make('password123'),
            'verified_at' => now(),
            'is_active' => true,
            'tenant_id' => $tenant->id, // Set tenant_id field
            'profile_photo_path' => 'https://source.unsplash.com/128x128/?face,portrait,person&sig=6',
            'settings' => json_encode(['theme' => 'light', 'notifications' => true])
        ]);
        $student->assignRole('student');
        $student->tenants()->attach($tenant->id, [
            'status' => 'active',
            'role_id' => $getRoleId('student'),
            'current_tenant' => false,
            'joined_at' => now(),
        ]);

        // 7. ACCOUNTANT (Assigned to tenant 1)
        $accountant = User::create([
            'name' => 'Contabilista Teste',
            'identifier' => 'accountant@example.com',
            'type' => 'email',
            'password' => Hash::make('password123'),
            'verified_at' => now(),
            'is_active' => true,
            'tenant_id' => $tenant->id, // Set tenant_id field
            'profile_photo_path' => 'https://source.unsplash.com/128x128/?face,portrait,person&sig=7',
            'settings' => json_encode(['theme' => 'light', 'notifications' => true])
        ]);
        $accountant->assignRole('accountant');
        $accountant->tenants()->attach($tenant->id, [
            'status' => 'active',
            'role_id' => $getRoleId('accountant'),
            'current_tenant' => false,
            'joined_at' => now(),
        ]);

        // 8. SECRETARY (Assigned to tenant 1)
        $secretary = User::create([
            'name' => 'Secretária Teste',
            'identifier' => 'secretary@example.com',
            'type' => 'email',
            'password' => Hash::make('password123'),
            'verified_at' => now(),
            'is_active' => true,
            'tenant_id' => $tenant->id, // Set tenant_id field
            'profile_photo_path' => 'https://source.unsplash.com/128x128/?face,portrait,person&sig=8',
            'settings' => json_encode(['theme' => 'light', 'notifications' => true])
        ]);
        $secretary->assignRole('secretary');
        $secretary->tenants()->attach($tenant->id, [
            'status' => 'active',
            'role_id' => $getRoleId('secretary'),
            'current_tenant' => false,
            'joined_at' => now(),
        ]);

        $this->command->info('Created 8 example users for all iEDU roles!');
        $this->command->info('All users (except super_admin) have been assigned to Tenant ID 1.');
        $this->command->info('School Owner has been set as current_tenant for Tenant ID 1.');
    }
}
