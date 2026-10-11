<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\TenantStatus;
use App\Enums\TenantType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Clients\StoreClientRequest;
use App\Http\Requests\Api\Admin\Clients\UpdateClientRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\ClientResource;
use App\Http\Resources\TenantUserResource;
use App\Models\AuditLog;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Clients\ClientManager;
use App\Services\Users\UserProfileService;
use App\Support\UserRoles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Clientes de la plataforma. Las consultas sobre users/contacts/campaigns
 * quitan el TenantScope a propósito: el tenant activo es la plataforma y aquí
 * se cuentan los datos de cada cliente. Solo lo alcanza el tenant de la
 * plataforma (middleware `platform` + `admin.clients.*`).
 */
class ClientController extends Controller
{
    public function __construct(private readonly ClientManager $clients) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 20), 100);

        $clients = $this->query()
            ->when($request->filled('type'), fn (Builder $q) => $q->where('type', TenantType::from($request->string('type')->toString())))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', TenantStatus::from($request->string('status')->toString())))
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $like = '%'.mb_strtolower($request->string('search')->toString()).'%';
                $q->where(fn (Builder $q) => $q
                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhere('document_number', 'like', $like)
                    ->orWhereRaw('LOWER(contact_email) LIKE ?', [$like]));
            })
            ->orderBy('name')
            ->paginate($perPage);

        return ClientResource::collection($clients);
    }

    public function show(Tenant $client): ClientResource
    {
        return new ClientResource($this->query()->findOrFail($client->id));
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = $this->clients->create($request->safe()->except('admin_photo'), $request->file('admin_photo'));

        return (new ClientResource($this->query()->findOrFail($client->id)))->response()->setStatusCode(201);
    }

    public function update(UpdateClientRequest $request, Tenant $client): ClientResource
    {
        $this->clients->update($client, $request->validated());

        return new ClientResource($this->query()->findOrFail($client->id));
    }

    public function suspend(Request $request, Tenant $client): ClientResource
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->clients->setStatus($client, TenantStatus::Suspended, $request->input('reason'));

        return new ClientResource($this->query()->findOrFail($client->id));
    }

    public function reactivate(Tenant $client): ClientResource
    {
        $this->clients->setStatus($client, TenantStatus::Active);

        return new ClientResource($this->query()->findOrFail($client->id));
    }

    public function users(Tenant $client): AnonymousResourceCollection
    {
        $users = User::query()->forTenant($client->id)->orderBy('name')->get();
        UserRoles::attach($users);

        return TenantUserResource::collection($users);
    }

    public function userPhoto(Tenant $client, int $user, UserProfileService $profiles): StreamedResponse
    {
        return $profiles->photoResponse(User::query()->forTenant($client->id)->findOrFail($user));
    }

    public function activity(Request $request, Tenant $client): AnonymousResourceCollection
    {
        $logs = AuditLog::query()
            ->with('user')
            ->where('tenant_id', $client->id)
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return AuditLogResource::collection($logs);
    }

    /** @return Builder<Tenant> */
    private function query(): Builder
    {
        $withoutTenantScope = fn (Builder $q) => $q->withoutGlobalScope(TenantScope::class);

        return Tenant::query()
            ->where('is_platform', false)
            ->withCount([
                'users' => $withoutTenantScope,
                'contacts' => $withoutTenantScope,
                'campaigns' => $withoutTenantScope,
            ]);
    }
}
