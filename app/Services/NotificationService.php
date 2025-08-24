<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\TenantInvitationMail;
use App\Models\Settings\Tenant;
use App\Models\User;
use App\Models\TenantInvitation;

class NotificationService
{
    /**
     * Send invitation notification
     *
     * @param TenantInvitation $invitation
     * @param Tenant $tenant
     * @param User $currentUser
     * @return void
     */
    public function sendInvitation(TenantInvitation $invitation, Tenant $tenant, User $currentUser): void
    {
        try {
            // Enhanced logging for production debugging
            Log::info('=== INVITATION EMAIL DEBUG START ===', [
                'invitation_id' => $invitation->id,
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'inviter_id' => $currentUser->id,
                'inviter_name' => $currentUser->name,
                'inviter_email' => $currentUser->email,
                'identifier' => $invitation->identifier,
                'type' => $invitation->type,
                'role' => $invitation->role,
                'environment' => app()->environment(),
                'app_url' => config('app.url'),
                'mail_driver' => config('mail.default'),
                'mail_from_address' => config('mail.from.address'),
                'mail_from_name' => config('mail.from.name'),
                'queue_connection' => config('queue.default'),
                'app_debug' => config('app.debug')
            ]);

            // Get role display name
            $roleDisplayName = $this->getRoleDisplayName($invitation->role);
            Log::info('Role display name resolved', [
                'invitation_id' => $invitation->id,
                'role' => $invitation->role,
                'role_display_name' => $roleDisplayName
            ]);
            
            // Generate accept URL (this would be your frontend URL)
            $acceptUrl = $this->generateAcceptUrl($invitation);
            
            // Format expiry date
            $expiryDate = $invitation->expires_at ? $invitation->expires_at->format('d/m/Y H:i') : '7 dias';
            Log::info('Expiry date formatted', [
                'invitation_id' => $invitation->id,
                'expires_at' => $invitation->expires_at,
                'formatted_expiry' => $expiryDate
            ]);
            
            // Log before sending email
            Log::info('About to send email via Mail facade', [
                'invitation_id' => $invitation->id,
                'to_email' => $invitation->identifier,
                'mail_class' => TenantInvitationMail::class,
                'accept_url' => $acceptUrl,
                'url_length' => strlen($acceptUrl)
            ]);
            
            // Send email
            Mail::to($invitation->identifier)
                ->send(new TenantInvitationMail(
                    $invitation,
                    $tenant,
                    $currentUser,
                    $roleDisplayName,
                    $acceptUrl,
                    $expiryDate
                ));
            
            Log::info('=== INVITATION EMAIL SENT SUCCESSFULLY ===', [
                'invitation_id' => $invitation->id,
                'to' => $invitation->identifier,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            Log::error('=== INVITATION EMAIL FAILED ===', [
                'invitation_id' => $invitation->id,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'environment' => app()->environment(),
                'mail_config' => [
                    'driver' => config('mail.default'),
                    'from_address' => config('mail.from.address'),
                    'from_name' => config('mail.from.name'),
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port'),
                    'encryption' => config('mail.mailers.smtp.encryption'),
                    'username' => config('mail.mailers.smtp.username') ? 'SET' : 'NOT_SET',
                    'password' => config('mail.mailers.smtp.password') ? 'SET' : 'NOT_SET'
                ]
            ]);
            
            // Don't throw the exception - we don't want to break the invitation process
            // if email fails
        }
    }

    /**
     * Get role display name
     */
    private function getRoleDisplayName(string $role): string
    {
        $roleNames = [
            'owner' => 'Proprietário da Organização',
            'admin' => 'Administrador',
            'project_manager' => 'Gerente de Projeto',
            'finance_manager' => 'Gerente Financeiro',
            'team_member' => 'Membro da Equipe',
            'viewer' => 'Visualizador'
        ];
        
        return $roleNames[$role] ?? $role;
    }

    /**
     * Generate accept URL for invitation
     */
    private function generateAcceptUrl(TenantInvitation $invitation): string
    {
        // Debug: Log environment and configuration details
        Log::info('Starting invitation URL generation', [
            'invitation_id' => $invitation->id,
            'environment' => app()->environment(),
            'app_url' => config('app.url'),
            'frontend_url_config' => config('app.frontend_url'),
            'env_frontend_url' => env('FRONTEND_URL'),
            'app_env' => env('APP_ENV'),
            'app_debug' => env('APP_DEBUG')
        ]);

        // Use the model's method for consistency
        $acceptUrl = $invitation->getAcceptUrl();
        
        // Debug: Log the final URL generation
        Log::info('Generated invitation accept URL', [
            'invitation_id' => $invitation->id,
            'environment' => app()->environment(),
            'frontend_url' => config('app.frontend_url'),
            'token' => $invitation->token,
            'final_url' => $acceptUrl,
            'url_length' => strlen($acceptUrl),
            'url_parts' => parse_url($acceptUrl)
        ]);
        
        return $acceptUrl;
    }

    /**
     * Send general notification
     *
     * @param string $type
     * @param array $data
     * @return void
     */
    public function sendNotification(string $type, array $data): void
    {
        Log::info("Notification sent: {$type}", $data);
        
        // TODO: Implement actual notification sending logic
    }
} 