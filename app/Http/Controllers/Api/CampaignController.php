<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Campaigns\StoreCampaignRequest;
use App\Http\Requests\Api\Campaigns\UpdateCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Jobs\DispatchCampaign;
use App\Models\Campaign;
use App\Models\Template;
use App\Services\CampaignAudienceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CampaignController extends Controller
{
    public function __construct(private readonly CampaignAudienceService $audience) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 20), 100);

        $campaigns = Campaign::query()
            ->with('template')
            ->withCount($this->recipientCountsByStatus())
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return CampaignResource::collection($campaigns);
    }

    public function store(StoreCampaignRequest $request): CampaignResource
    {
        $template = Template::query()->findOrFail($request->validated('template_id'));
        $scheduledAt = $request->validated('scheduled_at');

        $campaign = Campaign::create([
            'template_id' => $template->id,
            'channel' => $template->channel,
            'name' => $request->validated('name'),
            'status' => $scheduledAt !== null ? CampaignStatus::Scheduled : CampaignStatus::Draft,
            'scheduled_at' => $scheduledAt,
        ]);

        $this->audience->enroll($campaign, $request->validated('contact_ids'));

        return new CampaignResource($campaign->load('template')->loadCount($this->recipientCountsByStatus()));
    }

    public function show(Campaign $campaign): CampaignResource
    {
        $campaign->load(['template', 'recipients.contact'])->loadCount($this->recipientCountsByStatus());

        return new CampaignResource($campaign);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): CampaignResource
    {
        abort_unless(
            in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Scheduled], true),
            422,
            'Solo se pueden editar campañas en borrador o programadas, antes de que empiecen a enviarse.',
        );

        $template = Template::query()->findOrFail($request->validated('template_id'));
        $scheduledAt = $request->validated('scheduled_at');
        $contactIds = $request->validated('contact_ids');

        $campaign->update([
            'template_id' => $template->id,
            'channel' => $template->channel,
            'name' => $request->validated('name'),
            'status' => $scheduledAt !== null ? CampaignStatus::Scheduled : CampaignStatus::Draft,
            'scheduled_at' => $scheduledAt,
        ]);

        // La audiencia se reemplaza por completo: como todavía no se envió
        // nada (draft/scheduled), es seguro quitar los destinatarios que ya
        // no forman parte de la selección y matricular los nuevos.
        $campaign->recipients()->whereNotIn('contact_id', $contactIds)->delete();
        $this->audience->enroll($campaign, $contactIds);

        return new CampaignResource($campaign->load('template')->loadCount($this->recipientCountsByStatus()));
    }

    public function dispatch(Campaign $campaign): CampaignResource
    {
        abort_unless(
            in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Scheduled], true),
            422,
            'La campaña no se puede iniciar desde su estado actual.',
        );

        DispatchCampaign::dispatch($campaign->id);

        $campaign->load('template')->loadCount($this->recipientCountsByStatus());

        return new CampaignResource($campaign);
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        abort_unless(
            in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Scheduled], true),
            422,
            'Solo se pueden eliminar campañas en borrador o programadas, antes de que empiecen a enviarse.',
        );

        $campaign->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, \Closure>
     */
    private function recipientCountsByStatus(): array
    {
        return collect(CampaignRecipientStatus::cases())
            ->mapWithKeys(fn (CampaignRecipientStatus $status) => [
                "recipients as {$status->value}_count" => fn ($query) => $query->where('status', $status),
            ])
            ->all();
    }
}
