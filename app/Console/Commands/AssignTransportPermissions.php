<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AssignTransportPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transport:assign-permissions {email?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign transport permissions to a user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        if (!$email) {
            // List all users
            $users = User::select('id', 'name', 'identifier')->get();
            $this->info('Available users:');
            foreach ($users as $user) {
                $this->line("ID: {$user->id} - {$user->name} ({$user->identifier})");
            }
            
            $email = $this->ask('Enter user identifier to assign permissions');
        }
        
        $user = User::where('identifier', $email)->first();
        
        if (!$user) {
            $this->error("User with identifier {$email} not found!");
            return 1;
        }
        
        $this->info("Assigning transport permissions to: {$user->name} ({$user->identifier})");
        $this->info("User tenant_id: {$user->tenant_id}");
        
        // Get all transport permissions
        $transportPermissions = Permission::where('name', 'like', 'transport.%')
            ->where('guard_name', 'api')
            ->get();
            
        if ($transportPermissions->isEmpty()) {
            $this->error('No transport permissions found! Run the TransportPermissionsSeeder first.');
            return 1;
        }
        
        // Check if user has tenant_id
        if (!$user->tenant_id) {
            $this->error('User does not have a tenant_id! Cannot assign permissions.');
            return 1;
        }
        
        // Assign all transport permissions to the user
        $user->givePermissionTo($transportPermissions);
        
        $this->info('✅ Transport permissions assigned successfully!');
        $this->info("Assigned {$transportPermissions->count()} permissions:");
        
        foreach ($transportPermissions as $permission) {
            $this->line("  - {$permission->name}");
        }
        
        // Also assign Transport Administrator role if it exists
        $transportAdminRole = Role::where('name', 'Transport Administrator')
            ->where('guard_name', 'api')
            ->first();
            
        if ($transportAdminRole) {
            $user->assignRole($transportAdminRole);
            $this->info('✅ Transport Administrator role assigned!');
        }
        
        return 0;
    }
}