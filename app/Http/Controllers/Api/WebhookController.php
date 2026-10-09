<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Channel;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessMessageStatusUpdate;
use App\Models\ChannelAccount;
use App\Models\Scopes\TenantScope;
use App\Services\Channels\ChannelManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class WebhookController extends Controller
{
    public function __construct(private readonly ChannelManager $channels) {}

    public function handle(Request $request, string $channel): Response
    {
        $channelEnum = Channel::tryFrom($channel);

        if ($channelEnum === null) {
            abort(HttpResponse::HTTP_NOT_FOUND);
        }

        $driver = $this->channels->driver($channelEnum);

        // Sin firma válida → 403, siempre. §6.4.
        if (! $driver->verifyWebhookSignature($request)) {
            abort(HttpResponse::HTTP_FORBIDDEN);
        }

        // Responder rápido; el procesamiento (idempotente) va a la cola.
        foreach ($driver->parseWebhook($request) as $update) {
            ProcessMessageStatusUpdate::dispatch($channelEnum, $update);
        }

        return response()->noContent();
    }

    /**
     * Estados de un mensaje enviado con la cuenta propia de un cliente: la
     * firma se verifica con las credenciales de ESA cuenta.
     */
    public function handleAccount(Request $request, string $channel, int $account): Response
    {
        $channelEnum = Channel::tryFrom($channel) ?? abort(HttpResponse::HTTP_NOT_FOUND);

        // Pública y sin tenant activo: se busca la cuenta por id y canal.
        $channelAccount = ChannelAccount::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('channel', $channelEnum)
            ->findOrFail($account);

        $driver = $this->channels->driverForAccount($channelAccount);

        if (! $driver->verifyWebhookSignature($request, $channelAccount->toSenderIdentity())) {
            abort(HttpResponse::HTTP_FORBIDDEN);
        }

        foreach ($driver->parseWebhook($request) as $update) {
            ProcessMessageStatusUpdate::dispatch($channelEnum, $update);
        }

        return response()->noContent();
    }
}
