<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Settings\Tenant;

class FixUserTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:fix-tenant {identifier}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix user tenant assignment';

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
        $this->info("Current tenant_id: " . ($user->tenant_id ?? 'NULL'));
        
        // List available tenants
        $tenants = Tenant::select('id', 'name', 'domain')->get();
        $this->info('Available tenants:');
        foreach ($tenants as $tenant) {
            $this->line("ID: {$tenant->id} - {$tenant->name} ({$tenant->domain})");
        }
        
        if ($tenants->isEmpty()) {
            $this->error('No tenants found!');
            return 1;
        }
        
        // Use first tenant if only one exists
        if ($tenants->count() === 1) {
            $tenant = $tenants->first();
            $this->info("Using only available tenant: {$tenant->name}");
        } else {
            $tenantId = $this->ask('Enter tenant ID to assign to user');
            $tenant = $tenants->find($tenantId);
            
            if (!$tenant) {
                $this->error("Tenant with ID {$tenantId} not found!");
                return 1;
            }
        }
        
        // Update user tenant
        $user->update(['tenant_id' => $tenant->id]);
        
        $this->info("✅ User assigned to tenant: {$tenant->name} (ID: {$tenant->id})");
        
        return 0;
    }
}