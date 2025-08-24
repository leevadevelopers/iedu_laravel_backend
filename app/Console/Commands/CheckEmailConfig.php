<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class CheckEmailConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:check-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check email configuration for production debugging';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("=== EMAIL CONFIGURATION CHECK ===");
        $this->info("Environment: " . app()->environment());
        $this->info("Debug Mode: " . (config('app.debug') ? 'ON' : 'OFF'));
        
        $this->newLine();
        
        // Mail Configuration
        $this->info("📧 MAIL CONFIGURATION:");
        $this->table(['Setting', 'Value', 'Status'], [
            ['Default Driver', config('mail.default'), $this->getStatus(config('mail.default') === 'smtp')],
            ['From Address', config('mail.from.address'), $this->getStatus(!empty(config('mail.from.address')))],
            ['From Name', config('mail.from.name'), $this->getStatus(!empty(config('mail.from.name')))],
            ['SMTP Host', config('mail.mailers.smtp.host'), $this->getStatus(!empty(config('mail.mailers.smtp.host')))],
            ['SMTP Port', config('mail.mailers.smtp.port'), $this->getStatus(!empty(config('mail.mailers.smtp.port')))],
            ['SMTP Encryption', config('mail.mailers.smtp.encryption'), $this->getStatus(true)],
            ['SMTP Username', config('mail.mailers.smtp.username') ? 'SET' : 'NOT SET', $this->getStatus(!empty(config('mail.mailers.smtp.username')))],
            ['SMTP Password', config('mail.mailers.smtp.password') ? 'SET' : 'NOT SET', $this->getStatus(!empty(config('mail.mailers.smtp.password')))],
        ]);
        
        $this->newLine();
        
        // App Configuration
        $this->info("🌐 APP CONFIGURATION:");
        $this->table(['Setting', 'Value', 'Status'], [
            ['App URL', config('app.url'), $this->getStatus(!empty(config('app.url')))],
            ['Frontend URL', config('app.frontend_url'), $this->getStatus(!empty(config('app.frontend_url')))],
            ['Environment', config('app.env'), $this->getStatus(config('app.env') === 'production')],
        ]);
        
        $this->newLine();
        
        // Recommendations
        $this->info("💡 RECOMMENDATIONS:");
        
        if (config('mail.default') !== 'smtp') {
            $this->error("❌ Mail driver should be 'smtp' for production, currently: " . config('mail.default'));
        }
        
        if (empty(config('mail.mailers.smtp.username')) || empty(config('mail.mailers.smtp.password'))) {
            $this->error("❌ SMTP credentials are missing");
        }
        
        if (empty(config('app.frontend_url'))) {
            $this->warn("⚠️  Frontend URL not configured - invitation links may not work");
        }
        
        if (config('app.debug')) {
            $this->warn("⚠️  Debug mode is ON - should be OFF in production");
        }
        
        $this->newLine();
        $this->info("✅ Configuration check completed!");
    }
    
    private function getStatus($condition): string
    {
        return $condition ? '✅ OK' : '❌ ISSUE';
    }
}
