<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Template
 */
class TemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'category' => $this->category,
            'name' => $this->name,
            'body' => $this->body,
            'variables' => $this->variables,
            'provider_template_id' => $this->provider_template_id,
            'status' => $this->status,
            'requires_consent' => $this->requiresConsent(),
            'created_at' => $this->created_at,
        ];
    }
}
