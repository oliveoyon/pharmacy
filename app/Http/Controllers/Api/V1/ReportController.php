<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportCsvExporter;
use App\Services\Reports\OperationalReportService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly OperationalReportService $reportService,
        private readonly ReportCsvExporter $csvExporter,
    ) {
    }

    public function salesSummary(Request $request): JsonResponse
    {
        $organizationId = $this->tenantContext->organizationId();
        if (! $organizationId) {
            return response()->json(['message' => 'Organization context is required.'], 422);
        }

        $validated = $request->validate([
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        return response()->json(
            $this->reportService->salesSummary($validated)
        );
    }

    public function stockValuation(Request $request): JsonResponse
    {
        $organizationId = $this->tenantContext->organizationId();
        if (! $organizationId) {
            return response()->json(['message' => 'Organization context is required.'], 422);
        }

        $validated = $request->validate([
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
        ]);

        return response()->json(
            $this->reportService->stockValuation($validated)
        );
    }

    public function expiryAlerts(Request $request): JsonResponse
    {
        $organizationId = $this->tenantContext->organizationId();
        if (! $organizationId) {
            return response()->json(['message' => 'Organization context is required.'], 422);
        }

        $validated = $request->validate([
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'within_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        return response()->json(
            $this->reportService->expiryAlerts($validated)
        );
    }

    public function exportCsv(Request $request, string $reportType): StreamedResponse
    {
        $organizationId = $this->tenantContext->organizationId();
        if (! $organizationId) {
            abort(422, 'Organization context is required.');
        }

        if (! in_array($reportType, ['sales_summary', 'stock_valuation', 'expiry_alerts'], true)) {
            abort(422, 'Unsupported report type.');
        }

        $validated = $request->validate([
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'within_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $csv = $this->csvExporter->export($reportType, $validated);
        $filename = $reportType.'_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
