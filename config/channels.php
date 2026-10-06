<?php

declare(strict_types=1);

use App\Services\Channels\Email\EmailSendGridDriver;
use App\Services\Channels\Email\EmailSesDriver;
use App\Services\Channels\Email\EmailSmtpDriver;
use App\Services\Channels\Email\EmailTwilioDriver;
use App\Services\Channels\Sms\SmsTwilioDriver;
use App\Services\Channels\WhatsApp\WhatsAppCloudDriver;
use App\Services\Channels\WhatsApp\WhatsAppTwilioDriver;

return [
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'twilio'),
        'drivers' => [
            'twilio' => WhatsAppTwilioDriver::class,
            'cloud' => WhatsAppCloudDriver::class,
        ],
    ],
    'sms' => [
        'driver' => env('SMS_DRIVER', 'twilio'),
        'drivers' => [
            'twilio' => SmsTwilioDriver::class,
        ],
    ],
    'email' => [
        'driver' => env('EMAIL_DRIVER', 'sendgrid'),
        'drivers' => [
            'sendgrid' => EmailSendGridDriver::class,
            'twilio' => EmailTwilioDriver::class,
            'smtp' => EmailSmtpDriver::class,
            'ses' => EmailSesDriver::class,
        ],
    ],
];
