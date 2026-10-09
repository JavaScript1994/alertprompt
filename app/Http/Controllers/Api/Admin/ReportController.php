<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Reports\ReportRangeRequest;
use App\Models\Alert;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\Reports\MessagingReport;
use App\Support\CsvDownload;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reportes de todos los clientes, para la plataforma. */
class ReportController extends Controller
{
    public function index(ReportRangeRequest $request): JsonResponse
    {
        $report = $this->report($request);

        return response()->json(['data' => [
            ...$report->summary(),
            'by_tenant' => $report->byTenant(),
            'clients' => [
                'active' => Tenant::query()->where('is_platform', false)->where('status', 'active')->count(),
                'suspended' => Tenant::query()->where('is_platform', false)->where('status', 'suspended')->count(),
            ],
            'open_alerts' => Alert::query()->withoutGlobalScope(TenantScope::class)->whereNull('resolved_at')->count(),
        ]]);
    }

    public function export(ReportRangeRequest $request): StreamedResponse
    {
        return CsvDownload::campaigns(
            $this->report($request)->campaigns(limit: 10_000),
            "reporte-global-{$request->from()->toDateString()}-{$request->to()->toDateString()}.csv",
            includeTenant: true,
        );
    }

    private function report(ReportRangeRequest $request): MessagingReport
    {
        return new MessagingReport(null, $request->from(), $request->to());
    }
}
