<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\TemplateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Templates\PreviewTemplateRequest;
use App\Http\Requests\Api\Templates\StoreTemplateRequest;
use App\Http\Requests\Api\Templates\UpdateTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TemplateController extends Controller
{
    public function __construct(private readonly TemplateRenderer $renderer) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 20), 100);

        $templates = Template::query()
            ->when($request->filled('channel'), fn ($query) => $query->where('channel', $request->string('channel')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return TemplateResource::collection($templates);
    }

    public function store(StoreTemplateRequest $request): TemplateResource
    {
        $data = $request->validated();
        $data['variables'] = $this->renderer->extractVariables($data['body']);
        $data['status'] = TemplateStatus::Draft;

        return new TemplateResource(Template::create($data));
    }

    public function update(UpdateTemplateRequest $request, Template $template): TemplateResource
    {
        $data = $request->validated();
        $data['variables'] = $this->renderer->extractVariables($data['body']);

        $template->update($data);

        return new TemplateResource($template);
    }

    public function destroy(Template $template): JsonResponse
    {
        $template->delete();

        return response()->json(status: 204);
    }

    public function preview(PreviewTemplateRequest $request): JsonResponse
    {
        $body = $request->string('body')->toString();
        $sampleData = $request->input('sample_data', []);

        return response()->json([
            'variables' => $this->renderer->extractVariables($body),
            'missing' => $this->renderer->missingVariables($body, $sampleData),
            'rendered' => $this->renderer->render($body, $sampleData),
        ]);
    }
}
