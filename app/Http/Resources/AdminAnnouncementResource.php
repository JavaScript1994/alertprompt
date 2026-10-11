<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Announcement */
class AdminAnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->type->label(),
            'title' => $this->title,
            'body' => $this->body,
            'link_url' => $this->link_url,
            'link_label' => $this->link_label,
            'audience' => $this->audience,
            'plan_keys' => $this->plan_keys ?? [],
            'tenants' => $this->whenLoaded('tenants', fn () => $this->tenants->map(fn ($tenant) => ['id' => $tenant->id, 'name' => $tenant->name])->values()),
            'admins_only' => $this->admins_only,
            'published_at' => $this->published_at,
            'expires_at' => $this->expires_at,
            'resolved_at' => $this->resolved_at,
            'send_email' => $this->send_email,
            'emailed_at' => $this->emailed_at,
            'status' => $this->status(),
            'reads_count' => $this->whenCounted('reads'),
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'created_at' => $this->created_at,
        ];
    }
}
