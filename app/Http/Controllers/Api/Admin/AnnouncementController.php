<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AnnouncementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Announcements\SaveAnnouncementRequest;
use App\Http\Resources\AdminAnnouncementResource;
use App\Models\Announcement;
use App\Services\Announcements\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/** Administración → Comunicados. */
class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcements) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $items = Announcement::query()
            ->with(['tenants:id,name', 'author'])
            ->withCount('reads')
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('type', $type))
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 20), 100));

        return AdminAnnouncementResource::collection($items);
    }

    public function store(SaveAnnouncementRequest $request): JsonResponse
    {
        $announcement = $this->announcements->save(null, $request->validated(), $request->user());

        return (new AdminAnnouncementResource($announcement->loadCount('reads')))->response()->setStatusCode(201);
    }

    public function update(SaveAnnouncementRequest $request, Announcement $announcement): AdminAnnouncementResource
    {
        // Lo ya enviado por correo no se reescribe: se publica otro comunicado.
        if ($announcement->emailed_at !== null && $request->boolean('send_email')) {
            throw ValidationException::withMessages(['send_email' => 'Este comunicado ya se envió por correo.']);
        }

        return new AdminAnnouncementResource($this->announcements->save($announcement, $request->validated(), $request->user())->loadCount('reads'));
    }

    public function resolve(Announcement $announcement): AdminAnnouncementResource
    {
        if ($announcement->type !== AnnouncementType::Incident) {
            throw ValidationException::withMessages(['announcement' => 'Solo los incidentes se marcan como resueltos.']);
        }

        return new AdminAnnouncementResource($this->announcements->resolve($announcement)->load('tenants')->loadCount('reads'));
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json(status: 204);
    }

    /** Lecturas por cliente. */
    public function stats(Announcement $announcement): JsonResponse
    {
        return response()->json(['data' => $this->announcements->stats($announcement)]);
    }
}
