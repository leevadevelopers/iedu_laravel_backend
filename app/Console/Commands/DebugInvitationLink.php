<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TenantInvitation;
use Illuminate\Support\Facades\Log;

class DebugInvitationLink extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invitation:debug-link {invitation_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug invitation link generation for testing purposes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $invitationId = $this->argument('invitation_id');
        
        if ($invitationId) {
            $invitation = TenantInvitation::find($invitationId);
        } else {
            $invitation = TenantInvitation::latest()->first();
        }
        
        if (!$invitation) {
            $this->error('No invitation found. Please create an invitation first.');
            return 1;
        }
        
        $this->info("=== Invitation Link Debug Test ===");
        $this->info("Invitation ID: {$invitation->id}");
        $this->info("Token: {$invitation->token}");
        $this->info("Status: {$invitation->status}");
        $this->info("Expires at: {$invitation->expires_at}");
        $this->info("Environment: " . app()->environment());
        $this->info("App URL: " . config('app.url'));
        $this->info("Frontend URL (config): " . config('app.frontend_url'));
        $this->info("Frontend URL (env): " . env('FRONTEND_URL'));
        $this->newLine();
        
        // Test URL generation
        $acceptUrl = $invitation->getAcceptUrl();
        
        $this->info("Generated URL: {$acceptUrl}");
        $this->info("URL Length: " . strlen($acceptUrl));
        $this->info("Can be accepted: " . ($invitation->canBeAccepted() ? 'Yes' : 'No'));
        $this->info("Is pending: " . ($invitation->isPending() ? 'Yes' : 'No'));
        $this->info("Is expired: " . ($invitation->isExpired() ? 'Yes' : 'No'));
        $this->newLine();
        
        // Parse URL components
        $urlParts = parse_url($acceptUrl);
        $this->info("URL Components:");
        foreach ($urlParts as $key => $value) {
            $this->line("  {$key}: {$value}");
        }
        
        // URL format validation
        $this->newLine();
        $this->info("URL Format Validation:");
        
        // Check if URL contains hash routing
        if (strpos($acceptUrl, '/#/') !== false) {
            $this->line("  ✓ Hash routing format detected (/#/)");
        } else {
            $this->line("  ✗ Hash routing format missing");
        }
        
        // Check if URL contains token parameter
        if (strpos($acceptUrl, 'token=') !== false) {
            $this->line("  ✓ Token parameter detected");
        } else {
            $this->line("  ✗ Token parameter missing");
        }
        
        // Check if URL is valid
        if (filter_var($acceptUrl, FILTER_VALIDATE_URL)) {
            $this->line("  ✓ URL is valid");
        } else {
            $this->line("  ✗ URL is invalid");
        }
        
        $this->newLine();
        $this->info("=== Test Complete ===");
        
        return 0;
    }
}
