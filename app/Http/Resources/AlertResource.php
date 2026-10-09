<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Alert
 */
class AlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'severity' => $this->severity,
            'title' => $this->title,
            'message' => $this->message,
            // No se llama 'data': Laravel no envolvería el recurso en { data: … }.
            'details' => $this->data,
            'occurrences' => $this->occurrences,
            'campaign' => $this->campaign ? ['id' => $this->campaign->id, 'name' => $this->campaign->name] : null,
            'tenant' => $this->when($request->is('api/admin/*'), fn () => $this->tenant ? [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
            ] : null),
            'resolved_at' => $this->resolved_at,
            'resolved_by' => $this->resolver?->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
