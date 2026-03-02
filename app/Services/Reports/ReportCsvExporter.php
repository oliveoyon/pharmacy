<?php

namespace App\Services\Reports;

class ReportCsvExporter
{
    public function __construct(private readonly OperationalReportService $reportService)
    {
    }

    public function export(string $reportType, array $filters = []): string
    {
        return match ($reportType) {
            'sales_summary' => $this->salesSummaryCsv($filters),
            'stock_valuation' => $this->stockValuationCsv($filters),
            'expiry_alerts' => $this->expiryAlertsCsv($filters),
            default => throw new \InvalidArgumentException('Unsupported report type.'),
        };
    }

    private function salesSummaryCsv(array $filters): string
    {
        $data = $this->reportService->salesSummary($filters);
        $rows = [];

        $rows[] = ['Sales Summary'];
        $rows[] = ['Date From', $data['filters']['date_from']];
        $rows[] = ['Date To', $data['filters']['date_to']];
        $rows[] = ['Branch ID', $data['filters']['branch_id'] ?? 'All'];
        $rows[] = [];
        $rows[] = ['Totals'];
        foreach ($data['totals'] as $key => $value) {
            $rows[] = [$key, $value];
        }

        $rows[] = [];
        $rows[] = ['By Type'];
        $rows[] = ['invoice_type', 'invoice_count', 'grand_total'];
        foreach ($data['by_type'] as $item) {
            $rows[] = [$item['invoice_type'], $item['invoice_count'], $item['grand_total']];
        }

        $rows[] = [];
        $rows[] = ['Daily Trend'];
        $rows[] = ['sales_date', 'invoice_count', 'grand_total'];
        foreach ($data['daily_trend'] as $item) {
            $rows[] = [$item['sales_date'], $item['invoice_count'], $item['grand_total']];
        }

        return $this->toCsv($rows);
    }

    private function stockValuationCsv(array $filters): string
    {
        $data = $this->reportService->stockValuation($filters);
        $rows = [];

        $rows[] = ['Stock Valuation'];
        $rows[] = ['Branch ID', $data['filters']['branch_id'] ?? 'All'];
        $rows[] = [];
        $rows[] = ['Summary'];
        foreach ($data['summary'] as $key => $value) {
            $rows[] = [$key, $value];
        }

        $rows[] = [];
        $rows[] = [
            'branch_id',
            'branch_name',
            'product_id',
            'product_name',
            'sku',
            'batch_id',
            'batch_no',
            'expiry_date',
            'qty',
            'cost_price',
            'selling_price',
            'cost_value',
            'selling_value',
        ];

        foreach ($data['items'] as $item) {
            $rows[] = [
                $item['branch_id'],
                $item['branch_name'],
                $item['product_id'],
                $item['product_name'],
                $item['sku'],
                $item['batch_id'],
                $item['batch_no'],
                $item['expiry_date'],
                $item['qty'],
                $item['cost_price'],
                $item['selling_price'],
                $item['cost_value'],
                $item['selling_value'],
            ];
        }

        return $this->toCsv($rows);
    }

    private function expiryAlertsCsv(array $filters): string
    {
        $data = $this->reportService->expiryAlerts($filters);
        $rows = [];

        $rows[] = ['Expiry Alerts'];
        $rows[] = ['Branch ID', $data['filters']['branch_id'] ?? 'All'];
        $rows[] = ['Within Days', $data['filters']['within_days']];
        $rows[] = [];
        $rows[] = ['Summary'];
        foreach ($data['summary'] as $key => $value) {
            $rows[] = [$key, $value];
        }

        $rows[] = [];
        $rows[] = [
            'branch_id',
            'branch_name',
            'product_id',
            'product_name',
            'sku',
            'batch_id',
            'batch_no',
            'expiry_date',
            'days_left',
            'qty',
        ];
        foreach ($data['items'] as $item) {
            $rows[] = [
                $item['branch_id'],
                $item['branch_name'],
                $item['product_id'],
                $item['product_name'],
                $item['sku'],
                $item['batch_id'],
                $item['batch_no'],
                $item['expiry_date'],
                $item['days_left'],
                $item['qty'],
            ];
        }

        return $this->toCsv($rows);
    }

    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $content;
    }
}

