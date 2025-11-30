<?php

namespace App\Services\SMS;

use App\Models\Communication\SMSLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SMSService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $senderId;

    public function __construct()
    {
        $this->apiUrl = config('services.sms.api_url', '');
        $this->apiKey = config('services.sms.api_key', '');
        $this->senderId = config('services.sms.sender_id', '');
    }

    /**
     * Send a single SMS
     */
    public function send(string $recipient, string $message, ?string $templateId = null, ?int $schoolId = null): SMSLog
    {
        $smsLog = SMSLog::create([
            'recipient_phone' => $this->normalizePhone($recipient),
            'message' => $message,
            'template_id' => $templateId,
            'status' => 'pending',
            'school_id' => $schoolId,
            'sent_by' => auth('api')->id(),
        ]);

        try {
            $response = $this->makeApiCall($smsLog->recipient_phone, $message);

            $smsLog->update([
                'status' => $response['status'] ?? 'sent',
                'provider' => $this->getProviderName(),
                'provider_message_id' => $response['message_id'] ?? null,
                'provider_response' => json_encode($response),
                'cost' => $response['cost'] ?? $this->calculateCost($message),
                'sent_at' => now(),
            ]);

            return $smsLog->fresh();
        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'sms_log_id' => $smsLog->id,
                'error' => $e->getMessage(),
            ]);

            $smsLog->update([
                'status' => 'failed',
                'provider_response' => json_encode(['error' => $e->getMessage()]),
            ]);

            throw $e;
        }
    }

    /**
     * Send bulk SMS
     */
    public function sendBulk(array $recipients, string $message, ?string $templateId = null, ?int $schoolId = null): array
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($recipients as $recipient) {
            try {
                $phone = is_array($recipient) ? $recipient['phone'] : $recipient;
                $smsLog = $this->send($phone, $message, $templateId, $schoolId);
                $results[] = $smsLog;

                if ($smsLog->status === 'sent' || $smsLog->status === 'delivered') {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            } catch (\Exception $e) {
                $failureCount++;
                Log::error('Bulk SMS failed for recipient', [
                    'recipient' => $recipient,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'total' => count($recipients),
            'success' => $successCount,
            'failed' => $failureCount,
            'logs' => $results,
        ];
    }

    /**
     * Check SMS balance
     */
    public function getBalance(): float
    {
        $cacheKey = 'sms_balance_' . auth('api')->id();

        return Cache::remember($cacheKey, 300, function () {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ])->get($this->apiUrl . '/balance');

                if ($response->successful()) {
                    $data = $response->json();
                    return (float) ($data['balance'] ?? 0);
                }
            } catch (\Exception $e) {
                Log::error('Failed to get SMS balance', ['error' => $e->getMessage()]);
            }

            return 0.0;
        });
    }

    /**
     * Get SMS history
     */
    public function getHistory(?int $schoolId = null, ?string $status = null, ?int $limit = 50)
    {
        $query = SMSLog::query();

        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->with('sender')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Make API call to SMS provider
     */
    protected function makeApiCall(string $recipient, string $message): array
    {
        // This is a placeholder - replace with actual SMS provider API
        // Example for a generic SMS gateway:
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl . '/send', [
            'to' => $recipient,
            'message' => $message,
            'from' => $this->senderId,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'status' => 'sent',
                'message_id' => $data['message_id'] ?? null,
                'cost' => $data['cost'] ?? $this->calculateCost($message),
            ];
        }

        throw new \Exception('SMS API request failed: ' . $response->body());
    }

    /**
     * Normalize phone number
     */
    protected function normalizePhone(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add country code if missing (assuming Mozambique: +258)
        if (!str_starts_with($phone, '258')) {
            if (str_starts_with($phone, '0')) {
                $phone = '258' . substr($phone, 1);
            } elseif (strlen($phone) === 9) {
                $phone = '258' . $phone;
            }
        }

        return $phone;
    }

    /**
     * Calculate SMS cost (placeholder - adjust based on provider pricing)
     */
    protected function calculateCost(string $message): float
    {
        // Simple calculation: 1 SMS = 160 characters, cost per SMS
        $smsCount = ceil(strlen($message) / 160);
        $costPerSms = config('services.sms.cost_per_sms', 0.5);

        return $smsCount * $costPerSms;
    }

    /**
     * Get provider name
     */
    protected function getProviderName(): string
    {
        return config('services.sms.provider', 'generic');
    }
}

