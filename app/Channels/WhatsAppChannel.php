<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $message = $notification->toWhatsApp($notifiable);

        if (!$message) {
            return;
        }

        $phone = $this->getPhoneNumber($notifiable);

        if (!$phone) {
            Log::warning('No phone number available for WhatsApp notification', [
                'notifiable_id' => $notifiable->id,
                'notification' => get_class($notification)
            ]);
            return;
        }

        try {
            $this->sendWhatsAppMessage($phone, $message);
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp notification', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'notification' => get_class($notification)
            ]);
        }
    }

    private function getPhoneNumber($notifiable): ?string
    {
        // Try different phone number fields
        return $notifiable->whatsapp_phone ??
               $notifiable->phone ??
               $notifiable->mobile_phone ??
               null;
    }

    private function sendWhatsAppMessage(string $phone, string $message): void
    {
        // Example using Twilio WhatsApp API
        $twilioSid = config('services.twilio.sid');
        $twilioToken = config('services.twilio.token');
        $twilioWhatsAppFrom = config('services.twilio.whatsapp_from');

        if (!$twilioSid || !$twilioWhatsAppFrom) {
            throw new \Exception('WhatsApp/Twilio not configured');
        }

        $response = Http::withBasicAuth($twilioSid, $twilioToken)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$twilioSid}/Messages.json", [
                'From' => "whatsapp:{$twilioWhatsAppFrom}",
                'To' => "whatsapp:{$phone}",
                'Body' => $message
            ]);

        if (!$response->successful()) {
            throw new \Exception('WhatsApp API error: ' . $response->body());
        }

        Log::info('WhatsApp notification sent successfully', [
            'phone' => $phone,
            'message_length' => strlen($message)
        ]);
    }
}
