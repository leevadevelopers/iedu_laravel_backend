<?php

namespace App\Services\Payments;

use App\Models\V1\Financial\MobilePayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EMolaService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $apiSecret;
    protected string $callbackUrl;

    public function __construct()
    {
        $this->apiUrl = config('services.emola.api_url', '');
        $this->apiKey = config('services.emola.api_key', '');
        $this->apiSecret = config('services.emola.api_secret', '');
        $this->callbackUrl = config('services.emola.callback_url', '');
    }

    /**
     * Initiate e-Mola payment
     */
    public function initiatePayment(MobilePayment $mobilePayment): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/payment/initiate', [
                'amount' => (float) $mobilePayment->amount,
                'phone' => $mobilePayment->phone,
                'reference' => $mobilePayment->payment_id,
                'callback_url' => $this->callbackUrl,
                'description' => 'School Fee Payment',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                $mobilePayment->update([
                    'status' => 'initiated',
                    'reference_code' => $data['reference'] ?? null,
                    'instructions' => $this->generateInstructions($mobilePayment),
                    'provider_response' => json_encode($data),
                    'initiated_at' => now(),
                    'expires_at' => now()->addMinutes(10),
                ]);

                return [
                    'success' => true,
                    'payment_id' => $mobilePayment->payment_id,
                    'reference_code' => $mobilePayment->reference_code,
                    'instructions' => $mobilePayment->instructions,
                    'status' => $mobilePayment->status,
                ];
            }

            throw new \Exception('e-Mola API request failed: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('e-Mola payment initiation failed', [
                'mobile_payment_id' => $mobilePayment->id,
                'error' => $e->getMessage(),
            ]);

            $mobilePayment->update([
                'status' => 'failed',
                'provider_response' => json_encode(['error' => $e->getMessage()]),
            ]);

            throw $e;
        }
    }

    /**
     * Handle payment callback
     */
    public function handleCallback(array $callbackData): ?MobilePayment
    {
        try {
            $referenceCode = $callbackData['reference'] ?? null;
            $status = $callbackData['status'] ?? null;
            $transactionId = $callbackData['transaction_id'] ?? null;

            $mobilePayment = MobilePayment::where('reference_code', $referenceCode)->first();

            if (!$mobilePayment) {
                Log::warning('e-Mola callback: Mobile payment not found', ['reference_code' => $referenceCode]);
                return null;
            }

            if ($status === 'completed' || $status === 'success') {
                $mobilePayment->update([
                    'status' => 'completed',
                    'transaction_id' => $transactionId,
                    'completed_at' => now(),
                    'provider_response' => json_encode($callbackData),
                ]);

                return $mobilePayment;
            } elseif ($status === 'failed' || $status === 'cancelled') {
                $mobilePayment->update([
                    'status' => $status === 'cancelled' ? 'cancelled' : 'failed',
                    'provider_response' => json_encode($callbackData),
                ]);
            }

            return $mobilePayment;
        } catch (\Exception $e) {
            Log::error('e-Mola callback handling failed', [
                'callback_data' => $callbackData,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check payment status
     */
    public function checkStatus(MobilePayment $mobilePayment): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
            ])->get($this->apiUrl . '/payment/status', [
                'reference' => $mobilePayment->reference_code,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => $data['status'] ?? $mobilePayment->status,
                    'transaction_id' => $data['transaction_id'] ?? null,
                    'response' => $data,
                ];
            }

            return [
                'status' => $mobilePayment->status,
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('e-Mola status check failed', [
                'mobile_payment_id' => $mobilePayment->id,
                'error' => $e->getMessage(),
            ]);
            return [
                'status' => $mobilePayment->status,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get access token
     */
    protected function getAccessToken(): string
    {
        $cacheKey = 'emola_access_token';

        return cache()->remember($cacheKey, 3300, function () {
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->post($this->apiUrl . '/auth/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? '';
            }

            throw new \Exception('Failed to get e-Mola access token');
        });
    }

    /**
     * Generate payment instructions
     */
    protected function generateInstructions(MobilePayment $mobilePayment): string
    {
        return "Please complete the payment on your phone. You will receive an e-Mola prompt. Enter your PIN to complete the payment of MZN " . number_format($mobilePayment->amount, 2) . ".";
    }
}

