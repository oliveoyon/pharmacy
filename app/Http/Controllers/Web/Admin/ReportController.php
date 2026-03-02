<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Reports\OperationalReportService;
use App\Services\Reports\ReportCsvExporter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly OperationalReportService $reportService,
        private readonly ReportCsvExporter $csvExporter,
    ) {
    }

    public function index(Request $request): View
    {
        $organizationId = $this->organizationId($request);

        $validated = $request->validate([
            'section' => ['nullable', Rule::in(['sales_summary', 'stock_valuation', 'expiry_alerts'])],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'within_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $this->tenantContext->setOrganizationId($organizationId);

        $section = $validated['section'] ?? 'sales_summary';
        $branchId = $validated['branch_id'] ?? null;
        $dateFrom = $validated['date_from'] ?? now()->toDateString();
        $dateTo = $validated['date_to'] ?? now()->toDateString();
        $withinDays = (int) ($validated['within_days'] ?? 90);

        $filters = [
            'branch_id' => $branchId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'within_days' => $withinDays,
        ];

        $salesSummary = null;
        $stockValuation = null;
        $expiryAlerts = null;

        if ($section === 'sales_summary') {
            $salesSummary = $this->reportService->salesSummary($filters);
        } elseif ($section === 'stock_valuation') {
            $stockValuation = $this->reportService->stockValuation($filters);
        } else {
            $expiryAlerts = $this->reportService->expiryAlerts($filters);
        }

        $branches = Branch::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('web.admin.reports.index', [
            'branches' => $branches,
            'section' => $section,
            'filters' => $filters,
            'salesSummary' => $salesSummary,
            'stockValuation' => $stockValuation,
            'expiryAlerts' => $expiryAlerts,
        ]);
    }

    public function export(Request $request, string $reportType): StreamedResponse
    {
        $organizationId = $this->organizationId($request);
        abort_unless(in_array($reportType, ['sales_summary', 'stock_valuation', 'expiry_alerts'], true), 422);

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

        $this->tenantContext->setOrganizationId($organizationId);
        $csv = $this->csvExporter->export($reportType, $validated);
        $filename = $reportType.'_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function organizationId(Request $request): int
    {
        $organizationId = (int) ($request->user()?->organization_id ?? 0);
        abort_if($organizationId <= 0, 403, 'Organization context required.');

        return $organizationId;
    }
}
