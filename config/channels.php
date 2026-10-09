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
    /*
    | Remitente compartido: un cliente sin cuenta propia activa envía con el
    | número de la plataforma (variables TWILIO_*_FROM). Sirve para la demo;
    | en producción conviene apagarlo para que cada cliente use su número
    | (su calidad y su tier en Meta son suyos). Email siempre usa el
    | remitente de la plataforma.
    */
    'shared_sender' => (bool) env('CHANNELS_SHARED_SENDER', true),

    /*
    | Canales en los que un cliente puede tener número propio y qué
    | proveedores admite cada uno (claves de 'drivers').
    */
    'tenant_accounts' => [
        'whatsapp' => ['twilio', 'cloud'],
        'sms' => ['twilio'],
    ],

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
