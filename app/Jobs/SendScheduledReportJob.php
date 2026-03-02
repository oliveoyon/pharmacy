<?php

namespace App\Jobs;

use App\Mail\ScheduledReportMail;
use App\Models\ReportSchedule;
use App\Services\Reports\ReportCsvExporter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendScheduledReportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $reportScheduleId)
    {
    }

    public function handle(ReportCsvExporter $csvExporter, TenantContext $tenantContext): void
    {
        $schedule = ReportSchedule::withoutGlobalScopes()->find($this->reportScheduleId);
        if (! $schedule || ! $schedule->is_active) {
            return;
        }

        $tenantContext->setOrganizationId((int) $schedule->organization_id);

        $filters = $schedule->filters ?? [];
        $csv = $csvExporter->export($schedule->report_type, $filters);

        $dateSuffix = now()->format('Ymd_His');
        $fileName = $schedule->report_type.'_'.$dateSuffix.'.csv';

        $period = match ($schedule->frequency) {
            'daily' => now()->toDateString(),
            'weekly' => now()->startOfWeek()->toDateString().' to '.now()->endOfWeek()->toDateString(),
            'monthly' => now()->startOfMonth()->toDateString().' to '.now()->endOfMonth()->toDateString(),
            default => now()->toDateString(),
        };

        foreach (($schedule->recipients ?? []) as $email) {
            Mail::to($email)->send(new ScheduledReportMail(
                reportName: $schedule->name,
                reportType: $schedule->report_type,
                period: $period,
                csvContent: $csv,
                fileName: $fileName,
            ));
        }
    }
}

