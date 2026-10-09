<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Reports\ReportRangeRequest;
use App\Services\Reports\MessagingReport;
use App\Support\CsvDownload;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(ReportRangeRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->report($request)->summary()]);
    }

    public function export(ReportRangeRequest $request): StreamedResponse
    {
        return CsvDownload::campaigns(
            $this->report($request)->campaigns(limit: 10_000),
            "reporte-{$request->from()->toDateString()}-{$request->to()->toDateString()}.csv",
            includeTenant: false,
        );
    }

    private function report(ReportRangeRequest $request): MessagingReport
    {
        return new MessagingReport(TenantContext::id(), $request->from(), $request->to());
    }
}
