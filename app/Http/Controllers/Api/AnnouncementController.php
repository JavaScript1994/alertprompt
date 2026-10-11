<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Announcements\AnnouncementService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Comunicados que ve el usuario (campana, banners, Novedades). */
class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcements) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return AnnouncementResource::collection($this->announcements->visibleFor($request->user(), $this->tenant()));
    }

    public function read(Request $request, int $announcement): JsonResponse
    {
        $this->announcements->markRead($this->visible($request, $announcement), $request->user());

        return response()->json(status: 204);
    }

    /** Cierra el banner de una acción requerida (también la marca leída). */
    public function dismiss(Request $request, int $announcement): JsonResponse
    {
        $this->announcements->markRead($this->visible($request, $announcement), $request->user(), dismiss: true);

        return response()->json(status: 204);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->announcements->markAllRead($request->user(), $this->tenant());

        return response()->json(status: 204);
    }

    /** Solo se marca lo que el usuario puede ver (nada de ids ajenos). */
    private function visible(Request $request, int $id): Announcement
    {
        /** @var User $user */
        $user = $request->user();

        return $this->announcements->visibleFor($user, $this->tenant())->firstWhere('id', $id) ?? abort(404);
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail(TenantContext::id());
    }
}
