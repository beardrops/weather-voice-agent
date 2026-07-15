<?php

namespace App\Services;

use Twilio\Rest\Client;

class SMSService
{
    private ?Client $client = null;
    private ?string $fromNumber = null;

    public function __construct()
    {
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $this->fromNumber = config('services.twilio.phone_number');

        if ($accountSid && $authToken) {
            $this->client = new Client($accountSid, $authToken);
        }
    }

    public function send(string $body, string $toPhoneNumber): array
    {
        if (!$this->client) {
            logger()->warning('Twilio not configured — SMS not sent');
            return ['sent' => false, 'reason' => 'twilio_not_configured'];
        }

        if (!$this->fromNumber) {
            logger()->warning('TWILIO_PHONE_NUMBER not set — SMS not sent');
            return ['sent' => false, 'reason' => 'twilio_phone_missing'];
        }

        try {
            $message = $this->client->messages->create($toPhoneNumber, [
                'from' => $this->fromNumber,
                'body' => $body,
            ]);

            logger()->info('SMS sent', ['messageSid' => $message->sid, 'to' => substr($toPhoneNumber, 0, 6).'****']);

            return ['sent' => true, 'messageSid' => $message->sid];
        } catch (\Exception $e) {
            logger()->error('Twilio send failed: ' . $e->getMessage());
            return ['sent' => false, 'reason' => $e->getMessage()];
        }
    }
}