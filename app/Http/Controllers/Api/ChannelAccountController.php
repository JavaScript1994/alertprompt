<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Channel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChannelAccounts\RequestChannelAccountRequest;
use App\Http\Resources\ChannelAccountResource;
use App\Models\Tenant;
use App\Services\ChannelAccounts\ChannelAccountManager;
use App\Services\Channels\ChannelManager;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;

/** Número propio del cliente por canal (Configuración > Cuenta de WhatsApp). */
class ChannelAccountController extends Controller
{
    public function __construct(
        private readonly ChannelAccountManager $accounts,
        private readonly ChannelManager $channels,
    ) {}

    public function index(): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail(TenantContext::id());

        return response()->json(['data' => $this->summary($tenant)]);
    }

    public function request(RequestChannelAccountRequest $request): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail(TenantContext::id());

        $this->accounts->request(
            $tenant,
            Channel::from($request->string('channel')->toString()),
            $request->string('sender')->toString(),
            $request->input('display_name'),
        );

        return response()->json(['data' => $this->summary($tenant)]);
    }

    /** @return list<array<string, mixed>> */
    private function summary(Tenant $tenant): array
    {
        return collect(array_keys(config('channels.tenant_accounts')))
            ->map(function (string $value) use ($tenant) {
                $channel = Channel::from($value);
                $account = $this->accounts->find($tenant, $channel);

                return [
                    'channel' => $channel,
                    'account' => $account ? new ChannelAccountResource($account) : null,
                    'shared_sender_allowed' => $this->channels->sharedSenderAllowed($channel),
                    'can_send' => $this->channels->canSend($tenant->id, $channel),
                ];
            })
            ->values()
            ->all();
    }
}
