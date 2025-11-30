<?php

namespace App\Services\Payments;

use App\Models\V1\Financial\MobilePayment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $apiSecret;
    protected string $callbackUrl;

    public function __construct()
    {
        $this->apiUrl = config('services.mpesa.api_url', '');
        $this->apiKey = config('services.mpesa.api_key', '');
        $this->apiSecret = config('services.mpesa.api_secret', '');
        $this->callbackUrl = config('services.mpesa.callback_url', '');
    }

    /**
     * Initiate M-Pesa payment
     */
    public function initiatePayment(MobilePayment $mobilePayment): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/stk/push', [
                'BusinessShortCode' => config('services.mpesa.short_code', ''),
                'Password' => $this->generatePassword(),
                'Timestamp' => now()->format('YmdHis'),
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => (int) $mobilePayment->amount,
                'PartyA' => $mobilePayment->phone,
                'PartyB' => config('services.mpesa.short_code', ''),
                'PhoneNumber' => $mobilePayment->phone,
                'CallBackURL' => $this->callbackUrl,
                'AccountReference' => $mobilePayment->payment_id,
                'TransactionDesc' => 'School Fee Payment',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                $mobilePayment->update([
                    'status' => 'initiated',
                    'reference_code' => $data['CheckoutRequestID'] ?? null,
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

            throw new \Exception('M-Pesa API request failed: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('M-Pesa payment initiation failed', [
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
            $referenceCode = $callbackData['CheckoutRequestID'] ?? null;
            $resultCode = $callbackData['ResultCode'] ?? null;
            $transactionId = $callbackData['MpesaReceiptNumber'] ?? null;

            $mobilePayment = MobilePayment::where('reference_code', $referenceCode)->first();

            if (!$mobilePayment) {
                Log::warning('M-Pesa callback: Mobile payment not found', ['reference_code' => $referenceCode]);
                return null;
            }

            if ($resultCode == 0) {
                // Payment successful
                $mobilePayment->update([
                    'status' => 'completed',
                    'transaction_id' => $transactionId,
                    'completed_at' => now(),
                    'provider_response' => json_encode($callbackData),
                ]);

                return $mobilePayment;
            } else {
                // Payment failed
                $mobilePayment->update([
                    'status' => 'failed',
                    'provider_response' => json_encode($callbackData),
                ]);
            }

            return $mobilePayment;
        } catch (\Exception $e) {
            Log::error('M-Pesa callback handling failed', [
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
            ])->get($this->apiUrl . '/query', [
                'CheckoutRequestID' => $mobilePayment->reference_code,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => $this->mapStatus($data['ResultCode'] ?? null),
                    'transaction_id' => $data['MpesaReceiptNumber'] ?? null,
                    'response' => $data,
                ];
            }

            return [
                'status' => $mobilePayment->status,
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('M-Pesa status check failed', [
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
        $cacheKey = 'mpesa_access_token';

        return cache()->remember($cacheKey, 3300, function () {
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->get($this->apiUrl . '/oauth/v1/generate?grant_type=client_credentials');

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? '';
            }

            throw new \Exception('Failed to get M-Pesa access token');
        });
    }

    /**
     * Generate password for STK push
     */
    protected function generatePassword(): string
    {
        $shortCode = config('services.mpesa.short_code', '');
        $passkey = config('services.mpesa.passkey', '');
        $timestamp = now()->format('YmdHis');

        return base64_encode($shortCode . $passkey . $timestamp);
    }

    /**
     * Generate payment instructions
     */
    protected function generateInstructions(MobilePayment $mobilePayment): string
    {
        return "Please complete the payment on your phone. You will receive an M-Pesa prompt. Enter your PIN to complete the payment of MZN " . number_format($mobilePayment->amount, 2) . ".";
    }

    /**
     * Map M-Pesa result code to payment status
     */
    protected function mapStatus(?int $resultCode): string
    {
        return match ($resultCode) {
            0 => 'completed',
            1032 => 'cancelled',
            default => 'failed',
        };
    }
}

