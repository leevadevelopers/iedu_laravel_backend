<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray($request): array
    {
        $settings = is_string($this->settings) ? json_decode($this->settings, true) : $this->settings;
        
        // Ensure settings is an array
        if (!is_array($settings)) {
            $settings = [];
        }
        
        $features = $this->getFeatures();
        
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'subdomain' => $this->slug,
            'isActive' => $this->is_active,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            
            // Transform settings into expected frontend format
            'branding' => [
                'logo' => $this->getBrandingValue($settings, 'logo', ''),
                'favicon' => $this->getBrandingValue($settings, 'favicon', ''),
                'primaryColor' => $this->getBrandingValue($settings, 'primaryColor', '#3b82f6'),
                'secondaryColor' => $this->getBrandingValue($settings, 'secondaryColor', '#10b981'),
                'accentColor' => $this->getBrandingValue($settings, 'accentColor', '#8b5cf6'),
                'logoUrl' => $this->getBrandingValue($settings, 'logoUrl', $this->getBrandingValue($settings, 'logo', '')),
                'companyName' => $this->getBrandingValue($settings, 'companyName', $this->name),
                'shortName' => $this->getBrandingValue($settings, 'shortName', ''),
                'description' => $this->getBrandingValue($settings, 'description', ''),
                'website' => $this->getBrandingValue($settings, 'website', ''),
                'email' => $this->getBrandingValue($settings, 'email', ''),
                'phone' => $this->getBrandingValue($settings, 'phone', ''),
                'address' => $this->getBrandingValue($settings, 'address', ''),
                'customCSS' => $this->getBrandingValue($settings, 'customCSS', ''),
            ],
            
            'features' => [
                'maxUsers' => $features['maxUsers'] ?? 100,
                'maxCandidates' => $features['maxCandidates'] ?? 1000,
                'maxJobs' => $features['maxJobs'] ?? 50,
                'enabledModules' => $features['enabledModules'] ?? [],
                'customFields' => $features['customFields'] ?? false,
                'apiAccess' => $features['apiAccess'] ?? false,
                'webhooks' => $features['webhooks'] ?? false,
                'ssoIntegration' => $features['ssoIntegration'] ?? false,
                'whiteLabeling' => $features['whiteLabeling'] ?? false,
                'customDomains' => $features['customDomains'] ?? false,
            ],
            
            'settings' => [
                'timezone' => $settings['timezone'] ?? 'UTC',
                'dateFormat' => $settings['dateFormat'] ?? 'MM/DD/YYYY',
                'timeFormat' => $settings['timeFormat'] ?? 'HH:mm:ss',
                'currency' => $settings['currency'] ?? 'USD',
                'language' => $settings['language'] ?? 'en',
                'locale' => $settings['locale'] ?? 'en-US',
                'ui' => [
                    'theme' => (is_array($settings['ui'] ?? null) ? $settings['ui']['theme'] : null) ?? 'light',
                    'layout' => (is_array($settings['ui'] ?? null) ? $settings['ui']['layout'] : null) ?? 'classy',
                    'density' => (is_array($settings['ui'] ?? null) ? $settings['ui']['density'] : null) ?? 'standard',
                    'animations' => (is_array($settings['ui'] ?? null) ? $settings['ui']['animations'] : null) ?? true,
                    'showBreadcrumbs' => (is_array($settings['ui'] ?? null) ? $settings['ui']['showBreadcrumbs'] : null) ?? true,
                    'sidebarCollapsed' => (is_array($settings['ui'] ?? null) ? $settings['ui']['sidebarCollapsed'] : null) ?? false,
                    'showUserMenu' => (is_array($settings['ui'] ?? null) ? $settings['ui']['showUserMenu'] : null) ?? true,
                    'showNotifications' => (is_array($settings['ui'] ?? null) ? $settings['ui']['showNotifications'] : null) ?? true,
                    'showSearch' => (is_array($settings['ui'] ?? null) ? $settings['ui']['showSearch'] : null) ?? true,
                    'showHelp' => (is_array($settings['ui'] ?? null) ? $settings['ui']['showHelp'] : null) ?? true,
                    'showSettings' => (is_array($settings['ui'] ?? null) ? $settings['ui']['showSettings'] : null) ?? true,
                    'maxNotifications' => (is_array($settings['ui'] ?? null) ? $settings['ui']['maxNotifications'] : null) ?? 5,
                    'notificationTimeout' => (is_array($settings['ui'] ?? null) ? $settings['ui']['notificationTimeout'] : null) ?? 5000,
                ],
                'emailSettings' => (is_array($settings['emailSettings'] ?? null) ? $settings['emailSettings'] : [
                    'fromName' => $this->name,
                    'fromEmail' => 'noreply@' . $this->domain,
                    'replyTo' => 'support@' . $this->domain,
                ]),
                'notifications' => (is_array($settings['notifications'] ?? null) ? $settings['notifications'] : [
                    'emailEnabled' => true,
                    'smsEnabled' => false,
                    'pushEnabled' => false,
                    'slackIntegration' => false,
                ]),
            ],
            
            'permissions' => [
                'customRoles' => (is_array($settings['permissions'] ?? null) ? $settings['permissions']['customRoles'] : null) ?? [],
                'defaultPermissions' => (is_array($settings['permissions'] ?? null) ? $settings['permissions']['defaultPermissions'] : null) ?? [],
                'restrictedFeatures' => (is_array($settings['permissions'] ?? null) ? $settings['permissions']['restrictedFeatures'] : null) ?? [],
            ],
            
            'integrations' => [
                'enabled' => (is_array($settings['integrations'] ?? null) ? $settings['integrations']['enabled'] : null) ?? [],
                'configurations' => (is_array($settings['integrations'] ?? null) ? $settings['integrations']['configurations'] : null) ?? [],
            ],
            
            'billing' => [
                'plan' => (is_array($settings['billing'] ?? null) ? $settings['billing']['plan'] : null) ?? 'basic',
                'status' => (is_array($settings['billing'] ?? null) ? $settings['billing']['status'] : null) ?? 'active',
                'trialEnds' => (is_array($settings['billing'] ?? null) ? $settings['billing']['trialEnds'] : null) ?? null,
                'billingCycle' => (is_array($settings['billing'] ?? null) ? $settings['billing']['billingCycle'] : null) ?? 'monthly',
                'nextBillingDate' => (is_array($settings['billing'] ?? null) ? $settings['billing']['nextBillingDate'] : null) ?? null,
            ],
            
            // Additional fields for backward compatibility
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            'settings' => $this->settings,
            'features' => $this->getFeatures(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            'user_role' => $this->when(isset($this->user_role), $this->user_role),
            'is_current' => $this->when(isset($this->is_current), $this->is_current),
            'user_status' => $this->when(isset($this->user_status), $this->user_status),
            'joined_at' => $this->when(isset($this->joined_at), $this->joined_at),
            
            'owner' => $this->when($this->relationLoaded('owner'), function () {
                return new UserResource($this->owner());
            }),
            'users_count' => $this->when(isset($this->users_count), $this->users_count),
        ];
    }

    /**
     * Safely get branding value with fallback
     */
    private function getBrandingValue(array $settings, string $key, $default = null)
    {
        if (!is_array($settings['branding'] ?? null)) {
            return $default;
        }
        
        return $settings['branding'][$key] ?? $default;
    }
}