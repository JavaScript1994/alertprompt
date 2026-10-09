<?php

declare(strict_types=1);

namespace App\Services\Channels\Twilio;

use App\Services\Channels\SenderIdentity;
use Twilio\Rest\Client;

/**
 * Cliente de Twilio para un remitente: el de la cuenta principal por
 * defecto, o uno con las credenciales de la subcuenta del cliente.
 */
class TwilioClientFactory
{
    public function __construct(private readonly Client $default) {}

    public function for(?SenderIdentity $sender): Client
    {
        $sid = $sender?->credential('account_sid');
        $token = $sender?->credential('auth_token');

        return $sid !== null && $token !== null ? new Client($sid, $token) : $this->default;
    }

    public function authTokenFor(?SenderIdentity $sender): ?string
    {
        return $sender?->credential('auth_token') ?? config('services.twilio.token');
    }
}
