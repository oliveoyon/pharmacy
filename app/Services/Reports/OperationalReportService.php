<?php

namespace App\Services\Reports;

use App\Models\Batch;
use App\Models\SalesInvoice;
use App\Models\StockBalance;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OperationalReportService
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function salesSummary(array $filters): array
    {
        $this->ensureTenantContext();

        $dateFrom = isset($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : now()->startOfDay();
        $dateTo = isset($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : now()->endOfDay();
        $branchId = $filters['branch_id'] ?? null;

        $query = SalesInvoice::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('sold_at', [$dateFrom, $dateTo]);

        $totals = [
            'invoice_count' => (int) $query->count(),
            'sub_total' => (float) $query->sum('sub_total'),
            'tax_total' => (float) $query->sum('tax_total'),
            'discount_total' => (float) $query->sum('discount_total'),
            'grand_total' => (float) $query->sum('grand_total'),
            'paid_total' => (float) $query->sum('paid_total'),
            'due_total' => (float) $query->sum('due_total'),
        ];

        $byType = SalesInvoice::query()
            ->selectRaw('invoice_type, COUNT(*) as invoice_count, SUM(grand_total) as grand_total')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('sold_at', [$dateFrom, $dateTo])
            ->groupBy('invoice_type')
            ->get()
            ->map(fn ($row) => [
                'invoice_type' => $row->invoice_type,
                'invoice_count' => (int) $row->invoice_count,
                'grand_total' => (float) $row->grand_total,
            ]);

        $dailyTrend = SalesInvoice::query()
            ->selectRaw('DATE(sold_at) as sales_date, COUNT(*) as invoice_count, SUM(grand_total) as grand_total')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('sold_at', [$dateFrom, $dateTo])
            ->groupBy('sales_date')
            ->orderBy('sales_date')
            ->get()
            ->map(fn ($row) => [
                'sales_date' => $row->sales_date,
                'invoice_count' => (int) $row->invoice_count,
                'grand_total' => (float) $row->grand_total,
            ]);

        return [
            'filters' => [
                'branch_id' => $branchId ? (int) $branchId : null,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
            'totals' => $totals,
            'by_type' => $byType,
            'daily_trend' => $dailyTrend,
        ];
    }

    public function stockValuation(array $filters): array
    {
        $this->ensureTenantContext();

        $branchId = $filters['branch_id'] ?? null;

        $rows = StockBalance::query()
            ->with([
                'product:id,name,sku',
                'batch:id,batch_no,expiry_date,cost_price,selling_price',
                'branch:id,name',
            ])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('qty', '>', 0)
            ->get();

        $items = $rows->map(function (StockBalance $row): array {
            $qty = (float) $row->qty;
            $cost = (float) ($row->batch?->cost_price ?? 0);
            $sell = (float) ($row->batch?->selling_price ?? 0);

            return [
                'branch_id' => (int) $row->branch_id,
                'branch_name' => $row->branch?->name,
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product?->name,
                'sku' => $row->product?->sku,
                'batch_id' => (int) $row->batch_id,
                'batch_no' => $row->batch?->batch_no,
                'expiry_date' => optional($row->batch?->expiry_date)->toDateString(),
                'qty' => $qty,
                'cost_price' => $cost,
                'selling_price' => $sell,
                'cost_value' => $qty * $cost,
                'selling_value' => $qty * $sell,
            ];
        });

        return [
            'filters' => [
                'branch_id' => $branchId ? (int) $branchId : null,
            ],
            'summary' => [
                'line_count' => $items->count(),
                'total_qty' => $items->sum('qty'),
                'total_cost_value' => $items->sum('cost_value'),
                'total_selling_value' => $items->sum('selling_value'),
            ],
            'items' => $items,
        ];
    }

    public function expiryAlerts(array $filters): array
    {
        $this->ensureTenantContext();

        $branchId = $filters['branch_id'] ?? null;
        $withinDays = (int) ($filters['within_days'] ?? 90);
        if ($withinDays < 1 || $withinDays > 3650) {
            throw ValidationException::withMessages([
                'within_days' => 'within_days must be between 1 and 3650.',
            ]);
        }

        $today = now()->startOfDay();
        $endDate = now()->addDays($withinDays)->endOfDay();

        $batchIds = Batch::query()
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [$today->toDateString(), $endDate->toDateString()])
            ->pluck('id');

        $rows = StockBalance::query()
            ->with([
                'product:id,name,sku',
                'batch:id,batch_no,expiry_date',
                'branch:id,name',
            ])
            ->whereIn('batch_id', $batchIds)
            ->where('qty', '>', 0)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        $items = $rows->map(function (StockBalance $row): array {
            $expiryDate = $row->batch?->expiry_date;
            $daysLeft = $expiryDate ? now()->startOfDay()->diffInDays($expiryDate, false) : null;

            return [
                'branch_id' => (int) $row->branch_id,
                'branch_name' => $row->branch?->name,
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product?->name,
                'sku' => $row->product?->sku,
                'batch_id' => (int) $row->batch_id,
                'batch_no' => $row->batch?->batch_no,
                'expiry_date' => optional($expiryDate)->toDateString(),
                'days_left' => $daysLeft,
                'qty' => (float) $row->qty,
            ];
        })->sortBy('days_left')->values();

        return [
            'filters' => [
                'branch_id' => $branchId ? (int) $branchId : null,
                'within_days' => $withinDays,
            ],
            'summary' => [
                'line_count' => $items->count(),
                'total_qty' => $items->sum('qty'),
            ],
            'items' => $items,
        ];
    }

    private function ensureTenantContext(): void
    {
        if (! $this->tenantContext->organizationId()) {
            throw ValidationException::withMessages([
                'organization_id' => 'Organization context is required.',
            ]);
        }
    }
}

