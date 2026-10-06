<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Campaign
 */
class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stats = [
            'pending' => (int) ($this->pending_count ?? 0),
            'queued' => (int) ($this->queued_count ?? 0),
            'sent' => (int) ($this->sent_count ?? 0),
            'delivered' => (int) ($this->delivered_count ?? 0),
            'read' => (int) ($this->read_count ?? 0),
            'failed' => (int) ($this->failed_count ?? 0),
            'skipped' => (int) ($this->skipped_count ?? 0),
        ];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'channel' => $this->channel,
            'status' => $this->status,
            'template' => new TemplateResource($this->whenLoaded('template')),
            'stats' => [...$stats, 'total' => array_sum($stats)],
            'scheduled_at' => $this->scheduled_at,
            'created_at' => $this->created_at,
            'recipients' => $this->whenLoaded(
                'recipients',
                fn () => $this->recipients->map(fn ($recipient) => [
                    'contact_id' => $recipient->contact_id,
                    'name' => $recipient->contact->name,
                    'phone' => $recipient->contact->phone,
                    'email' => $recipient->contact->email,
                ]),
            ),
        ];
    }
}
