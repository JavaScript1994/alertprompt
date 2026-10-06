<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Consent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Consent
 */
class ConsentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'granted_at' => $this->granted_at,
            'revoked_at' => $this->revoked_at,
            'is_active' => $this->isActive(),
            'source' => $this->source,
            'evidence_text' => $this->evidence_text,
        ];
    }
}
