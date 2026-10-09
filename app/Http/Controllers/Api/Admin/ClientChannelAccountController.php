<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Channel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChannelAccounts\ConfigureChannelAccountRequest;
use App\Http\Resources\ChannelAccountResource;
use App\Models\Tenant;
use App\Services\ChannelAccounts\ChannelAccountManager;
use Illuminate\Http\JsonResponse;

class ClientChannelAccountController extends Controller
{
    public function __construct(private readonly ChannelAccountManager $accounts) {}

    public function index(Tenant $client): JsonResponse
    {
        return response()->json(['data' => collect(config('channels.tenant_accounts'))
            ->map(fn (array $providers, string $value) => [
                'channel' => $value,
                'providers' => $providers,
                'account' => ($account = $this->accounts->find($client, Channel::from($value))) ? new ChannelAccountResource($account) : null,
            ])
            ->values()]);
    }

    public function update(ConfigureChannelAccountRequest $request, Tenant $client, string $channel): ChannelAccountResource
    {
        $channelEnum = Channel::tryFrom($channel);
        abort_unless($channelEnum !== null && array_key_exists($channel, config('channels.tenant_accounts')), 404);

        return new ChannelAccountResource($this->accounts->configure($client, $channelEnum, $request->validated()));
    }
}
