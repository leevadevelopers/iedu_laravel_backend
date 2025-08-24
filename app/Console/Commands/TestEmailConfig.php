<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\TenantInvitationMail;
use App\Models\TenantInvitation;
use App\Models\Settings\Tenant;
use App\Models\User;

class TestEmailConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email-config {email?} {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email configuration and diagnose SSL issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email') ?? 'test@example.com';
        $debug = $this->option('debug');

        $this->info('=== EMAIL CONFIGURATION TEST ===');
        $this->info('Testing email configuration...');

        // Check current mail configuration
        $this->info("\n📧 Current Mail Configuration:");
        $this->info('Default Mailer: ' . config('mail.default'));
        $this->info('From Address: ' . config('mail.from.address'));
        $this->info('From Name: ' . config('mail.from.name'));
        
        if (config('mail.default') === 'smtp') {
            $this->info('SMTP Host: ' . config('mail.mailers.smtp.host'));
            $this->info('SMTP Port: ' . config('mail.mailers.smtp.port'));
            $this->info('SMTP Username: ' . config('mail.mailers.smtp.username'));
            $this->info('SMTP Encryption: ' . (config('mail.mailers.smtp.encryption') ?? 'tls'));
        }

        // Test DNS resolution
        $this->info("\n🌐 DNS Resolution Test:");
        $host = config('mail.mailers.smtp.host');
        if ($host) {
            $ips = gethostbynamel($host);
            if ($ips) {
                $this->info("✓ {$host} resolves to: " . implode(', ', $ips));
            } else {
                $this->error("✗ Cannot resolve {$host}");
            }
        }

        // Test SMTP connection
        $this->info("\n🔌 SMTP Connection Test:");
        try {
            $host = config('mail.mailers.smtp.host');
            $port = config('mail.mailers.smtp.port');
            
            if ($host && $port) {
                $connection = @fsockopen($host, $port, $errno, $errstr, 10);
                if ($connection) {
                    $this->info("✓ Successfully connected to {$host}:{$port}");
                    fclose($connection);
                } else {
                    $this->error("✗ Failed to connect to {$host}:{$port} - {$errstr} ({$errno})");
                }
            }
        } catch (\Exception $e) {
            $this->error("✗ Connection test failed: " . $e->getMessage());
        }

        // Test SSL certificate
        $this->info("\n🔒 SSL Certificate Test:");
        try {
            $host = config('mail.mailers.smtp.host');
            $port = config('mail.mailers.smtp.port');
            
            if ($host && $port) {
                $context = stream_context_create([
                    'ssl' => [
                        'capture_peer_cert' => true,
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ]
                ]);
                
                $connection = @stream_socket_client(
                    "ssl://{$host}:{$port}",
                    $errno,
                    $errstr,
                    10,
                    STREAM_CLIENT_CONNECT,
                    $context
                );
                
                if ($connection) {
                    $this->info("✓ SSL connection successful");
                    
                    // Get certificate info
                    $cert = stream_context_get_params($connection);
                    if (isset($cert['options']['ssl']['peer_certificate'])) {
                        $certInfo = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);
                        $this->info("Certificate CN: " . ($certInfo['subject']['CN'] ?? 'Unknown'));
                        $this->info("Certificate Issuer: " . ($certInfo['issuer']['CN'] ?? 'Unknown'));
                    }
                    
                    fclose($connection);
                } else {
                    $this->error("✗ SSL connection failed: {$errstr} ({$errno})");
                }
            }
        } catch (\Exception $e) {
            $this->error("✗ SSL test failed: " . $e->getMessage());
        }

        // Test actual email sending
        $this->info("\n📤 Email Sending Test:");
        try {
            // Create a simple test email
            Mail::raw('This is a test email from IPM system', function ($message) use ($email) {
                $message->to($email)
                        ->subject('IPM Email Configuration Test')
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });
            
            $this->info("✓ Test email sent successfully to {$email}");
            
            if ($debug) {
                Log::info('Email test completed successfully', [
                    'to' => $email,
                    'from' => config('mail.from.address'),
                    'mailer' => config('mail.default')
                ]);
            }
            
        } catch (\Exception $e) {
            $this->error("✗ Email sending failed: " . $e->getMessage());
            
            if ($debug) {
                Log::error('Email test failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->info("\n=== TEST COMPLETED ===");
        
        if ($debug) {
            $this->info("Check logs for detailed information: storage/logs/laravel.log");
        }
    }
}
