<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class TwilioChannel
{
    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification)
    {
        try {
            if (method_exists($notification, 'toTwilio')) {
                $message = $notification->toTwilio($notifiable);

                if (!$message) {
                    Log::warning('Twilio message is empty', [
                        'notification' => get_class($notification),
                        'notifiable' => get_class($notifiable)
                    ]);
                    return;
                }

                $this->sendSms($notifiable, $message);
            }
        } catch (Exception $e) {
            Log::error('Failed to send Twilio notification', [
                'notification' => get_class($notification),
                'notifiable' => get_class($notifiable),
                'error' => $e->getMessage()
            ]);

            // Re-throw the exception to mark the notification as failed
            throw $e;
        }
    }

    /**
     * Send SMS via Twilio API
     */
    protected function sendSms($notifiable, $message): void
    {
        $phoneNumber = $this->getPhoneNumber($notifiable);

        if (!$phoneNumber) {
            Log::warning('No phone number found for notifiable', [
                'notifiable' => get_class($notifiable),
                'id' => $notifiable->id ?? 'unknown'
            ]);
            return;
        }

        $twilioConfig = config('services.twilio');

        if (!$twilioConfig || !$twilioConfig['sid'] || !$twilioConfig['token']) {
            Log::error('Twilio configuration missing or incomplete');
            return;
        }

        $payload = [
            'From' => $twilioConfig['from'],
            'To' => $this->formatPhoneNumber($phoneNumber),
            'Body' => $this->extractMessageContent($message)
        ];

        $response = Http::withBasicAuth($twilioConfig['sid'], $twilioConfig['token'])
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$twilioConfig['sid']}/Messages.json", $payload);

        if ($response->successful()) {
            $responseData = $response->json();
            Log::info('SMS sent successfully via Twilio', [
                'message_sid' => $responseData['sid'] ?? null,
                'to' => $phoneNumber,
                'status' => $responseData['status'] ?? null
            ]);
        } else {
            Log::error('Failed to send SMS via Twilio', [
                'response' => $response->body(),
                'status' => $response->status(),
                'to' => $phoneNumber
            ]);

            throw new Exception('Failed to send SMS: ' . $response->body());
        }
    }

    /**
     * Get phone number from notifiable
     */
    protected function getPhoneNumber($notifiable): ?string
    {
        // Check various possible phone number fields
        $phoneFields = ['phone', 'phone_number', 'mobile', 'mobile_number', 'cell_phone'];

        foreach ($phoneFields as $field) {
            if (isset($notifiable->$field) && !empty($notifiable->$field)) {
                return $notifiable->$field;
            }
        }

        // Check if notifiable has a route method for phone
        if (method_exists($notifiable, 'routeNotificationForTwilio')) {
            return $notifiable->routeNotificationForTwilio();
        }

        return null;
    }

    /**
     * Format phone number for Twilio
     */
    protected function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Ensure it starts with country code
        if (strlen($cleaned) === 10) {
            // Assume US number, add +1
            return '+1' . $cleaned;
        } elseif (strlen($cleaned) === 11 && $cleaned[0] === '1') {
            // US number with country code
            return '+' . $cleaned;
        } elseif (strlen($cleaned) > 11) {
            // International number
            return '+' . $cleaned;
        }

        // Return as is if we can't determine format
        return $phoneNumber;
    }

    /**
     * Extract message content from various message formats
     */
    protected function extractMessageContent($message): string
    {
        if (is_string($message)) {
            return $message;
        }

        if (is_array($message) && isset($message['content'])) {
            return $message['content'];
        }

        if (is_object($message) && method_exists($message, 'getContent')) {
            return $message->getContent();
        }

        if (is_object($message) && property_exists($message, 'content')) {
            return $message->content;
        }

        // Fallback to string conversion
        return (string) $message;
    }
}
