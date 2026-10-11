<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Comunicado tal como lo ve un usuario del cliente, con su estado de
 * lectura (las `reads` deben venir filtradas por ese usuario).
 *
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $read = $this->reads->first();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->type->label(),
            'title' => $this->title,
            'body' => $this->body,
            'link_url' => $this->link_url,
            'link_label' => $this->link_label,
            'published_at' => $this->published_at,
            'resolved_at' => $this->resolved_at,
            'read_at' => $read?->read_at,
            'dismissed_at' => $read?->dismissed_at,
        ];
    }
}
