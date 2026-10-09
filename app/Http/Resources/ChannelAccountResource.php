<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ChannelAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Las credenciales nunca salen: solo pistas enmascaradas para la plataforma.
 *
 * @mixin ChannelAccount
 */
class ChannelAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $forPlatform = $request->is('api/admin/*');

        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'provider' => $this->provider,
            'display_name' => $this->display_name,
            'sender' => $this->sender,
            'status' => $this->status,
            'quality_rating' => $this->quality_rating,
            'messaging_tier' => $this->messaging_tier,
            'activated_at' => $this->activated_at,
            'notes' => $this->when($forPlatform, $this->notes),
            'credential_hints' => $this->when($forPlatform, fn () => $this->credentialHints()),
            // Para configurar en el proveedor la URL de estados de esta cuenta.
            'webhook_url' => $this->when($forPlatform, fn () => route('webhooks.account', [
                'channel' => $this->channel->value,
                'account' => $this->id,
            ])),
        ];
    }
}
