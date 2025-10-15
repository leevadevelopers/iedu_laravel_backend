<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AssignTransportRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transport:assign-role {identifier}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign Transport Administrator role to user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $identifier = $this->argument('identifier');
        
        $user = User::where('identifier', $identifier)->first();
        
        if (!$user) {
            $this->error("User with identifier {$identifier} not found!");
            return 1;
        }
        
        $this->info("User: {$user->name} ({$user->identifier})");
        $this->info("Tenant ID: {$user->tenant_id}");
        
        // Get Transport Administrator role
        $role = Role::where('name', 'Transport Administrator')
            ->where('guard_name', 'api')
            ->first();
            
        if (!$role) {
            $this->error('Transport Administrator role not found!');
            return 1;
        }
        
        $this->info("Role found: {$role->name}");
        
        // Assign role to user
        $user->assignRole($role);
        
        $this->info('✅ Transport Administrator role assigned successfully!');
        
        // Verify assignment
        $hasRole = $user->hasRole('Transport Administrator');
        $this->info("User has role: " . ($hasRole ? 'YES' : 'NO'));
        
        return 0;
    }
}