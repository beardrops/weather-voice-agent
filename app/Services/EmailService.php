<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

class EmailService
{
    public function send(string $body): array
    {
        $toEmail = 'dorin.roseti@gmail.com';
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        if (!$toEmail) {
            logger()->warning('EMAIL_TO_ADDRESS not set — email not sent');
            return ['sent' => false, 'reason' => 'email_to_address_missing'];
        }

        if (!$fromAddress) {
            logger()->warning('MAIL_FROM_ADDRESS not set — email not sent');
            return ['sent' => false, 'reason' => 'mail_from_address_missing'];
        }

        try {
            Mail::raw($body, function ($message) use ($toEmail, $fromAddress, $fromName) {
                $message->to($toEmail)
                    ->subject('Weather Update')
                    ->from($fromAddress, $fromName);
            });

            logger()->info('Email sent', ['to' => substr($toEmail, 0, 3) . '***@***']);

            return ['sent' => true];
        } catch (\Exception $e) {
            logger()->error('Email send failed: ' . $e->getMessage());
            return ['sent' => false, 'reason' => $e->getMessage()];
        }
    }
}

