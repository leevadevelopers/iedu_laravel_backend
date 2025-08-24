<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use App\Mail\TenantInvitationMail;
use App\Models\TenantInvitation;
use App\Models\Settings\Tenant;
use App\Models\User;

class TestInvitationEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:invitation-email {email} {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test invitation email sending with detailed debugging';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $debug = $this->option('debug');

        $this->info("=== TESTING INVITATION EMAIL ===");
        $this->info("Target email: {$email}");
        $this->info("Environment: " . app()->environment());
        $this->info("Debug mode: " . ($debug ? 'ON' : 'OFF'));

        // Log mail configuration
        $this->logMailConfiguration();

        // Create test data
        $testData = $this->createTestData($email);

        if ($debug) {
            $this->info("Test data created:");
            $this->table(['Field', 'Value'], [
                ['Invitation ID', $testData['invitation']->id],
                ['Tenant Name', $testData['tenant']->name],
                ['Inviter Name', $testData['inviter']->name],
                ['Role', $testData['roleDisplayName']],
                ['Accept URL', $testData['acceptUrl']],
                ['Expiry Date', $testData['expiryDate']],
            ]);
        }

        try {
            $this->info("Sending test email...");
            
            // Send the email
            Mail::to($email)->send(new TenantInvitationMail(
                $testData['invitation'],
                $testData['tenant'],
                $testData['inviter'],
                $testData['roleDisplayName'],
                $testData['acceptUrl'],
                $testData['expiryDate']
            ));

            $this->info("✅ Email sent successfully!");
            
            // Check if email was actually sent or just logged
            $mailDriver = config('mail.default');
            if ($mailDriver === 'log') {
                $this->warn("⚠️  Email was logged to storage/logs/laravel.log (not actually sent)");
                $this->info("Check the log file for email content");
            } else {
                $this->info("📧 Email was sent via {$mailDriver} driver");
            }

        } catch (\Exception $e) {
            $this->error("❌ Failed to send email: " . $e->getMessage());
            $this->error("Error details: " . $e->getTraceAsString());
            
            Log::error('Test invitation email failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        $this->info("=== TEST COMPLETED ===");
    }

    private function logMailConfiguration()
    {
        $this->info("Mail Configuration:");
        $this->table(['Setting', 'Value'], [
            ['Default Driver', config('mail.default')],
            ['From Address', config('mail.from.address')],
            ['From Name', config('mail.from.name')],
            ['SMTP Host', config('mail.mailers.smtp.host')],
            ['SMTP Port', config('mail.mailers.smtp.port')],
            ['SMTP Encryption', config('mail.mailers.smtp.encryption')],
            ['SMTP Username', config('mail.mailers.smtp.username') ? 'SET' : 'NOT SET'],
            ['SMTP Password', config('mail.mailers.smtp.password') ? 'SET' : 'NOT SET'],
        ]);

        Log::info('Test invitation email - Mail configuration', [
            'default_driver' => config('mail.default'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'smtp_host' => config('mail.mailers.smtp.host'),
            'smtp_port' => config('mail.mailers.smtp.port'),
            'smtp_encryption' => config('mail.mailers.smtp.encryption'),
            'smtp_username_set' => !empty(config('mail.mailers.smtp.username')),
            'smtp_password_set' => !empty(config('mail.mailers.smtp.password')),
        ]);
    }

    private function createTestData($email)
    {
        // Create or get a test tenant
        $tenant = Tenant::first() ?? Tenant::create([
            'name' => 'Test Organization',
            'domain' => 'test.local',
            'settings' => json_encode(['timezone' => 'UTC'])
        ]);

        // Create or get a test user
        $inviter = User::first() ?? User::create([
            'name' => 'Test Inviter',
            'email' => 'inviter@test.com',
            'identifier' => 'inviter@test.com',
            'password' => bcrypt('password')
        ]);

        // Create a test invitation
        $invitation = TenantInvitation::create([
            'tenant_id' => $tenant->id,
            'identifier' => $email,
            'type' => 'email',
            'role' => 'team_member',
            'inviter_id' => $inviter->id,
            'message' => 'Test invitation message',
            'token' => 'test-token-' . time(),
            'expires_at' => now()->addDays(7)
        ]);

        $roleDisplayName = 'Membro da Equipe';
        $acceptUrl = config('app.frontend_url', 'http://localhost:4200') . '/invitation/accept?token=' . $invitation->token;
        $expiryDate = $invitation->expires_at->format('d/m/Y H:i');

        return [
            'invitation' => $invitation,
            'tenant' => $tenant,
            'inviter' => $inviter,
            'roleDisplayName' => $roleDisplayName,
            'acceptUrl' => $acceptUrl,
            'expiryDate' => $expiryDate
        ];
    }
}
