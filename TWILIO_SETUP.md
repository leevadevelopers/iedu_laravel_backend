# Twilio SMS Integration Setup

This project includes custom Twilio SMS integration for sending transport notifications.

## Environment Variables

Add the following variables to your `.env` file:

```env
# Twilio Configuration
TWILIO_SID=your_twilio_account_sid
TWILIO_AUTH_TOKEN=your_twilio_auth_token
TWILIO_FROM_NUMBER=+1234567890
TWILIO_VERIFY_SERVICE_SID=your_twilio_verify_service_sid
```

## Configuration

The Twilio configuration is stored in `config/services.php`:

```php
'twilio' => [
    'sid' => env('TWILIO_SID'),
    'token' => env('TWILIO_AUTH_TOKEN'),
    'from' => env('TWILIO_FROM_NUMBER'),
    'verify_service_sid' => env('TWILIO_VERIFY_SERVICE_SID'),
],
```

## Service Provider

Register the `TwilioServiceProvider` in `config/app.php`:

```php
'providers' => [
    // ... other providers
    App\Providers\TwilioServiceProvider::class,
],
```

## Usage

### In Notifications

```php
use App\Notifications\Messages\TwilioSmsMessage;

public function toTwilio($notifiable)
{
    return TwilioSmsMessage::create()
        ->content('Your SMS message here')
        ->from('+1234567890');
}
```

### Channel Registration

The custom Twilio channel is automatically registered as 'twilio' and can be used in notifications:

```php
public function via($notifiable)
{
    return ['mail', 'twilio', 'database'];
}
```

## Features

- **Automatic Phone Number Detection**: Automatically finds phone numbers from various user fields
- **Phone Number Formatting**: Automatically formats phone numbers for international SMS
- **MMS Support**: Supports media messages with automatic MMS detection
- **Error Handling**: Comprehensive error handling and logging
- **Fluent Interface**: Easy-to-use message building interface

## Phone Number Fields

The system automatically detects phone numbers from these fields:
- `phone`
- `phone_number`
- `mobile`
- `mobile_number`
- `cell_phone`

## Custom Phone Number Routing

Implement `routeNotificationForTwilio()` method in your notifiable model:

```php
public function routeNotificationForTwilio()
{
    return $this->custom_phone_field;
}
```
