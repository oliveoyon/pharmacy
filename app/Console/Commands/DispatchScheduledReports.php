<?php

namespace App\Console\Commands;

use App\Jobs\SendScheduledReportJob;
use App\Models\ReportSchedule;
use App\Services\Reports\ReportScheduleService;
use Illuminate\Console\Command;

class DispatchScheduledReports extends Command
{
    protected $signature = 'reports:dispatch-scheduled';

    protected $description = 'Dispatch due scheduled report emails';

    public function handle(ReportScheduleService $scheduleService): int
    {
        $dueSchedules = ReportSchedule::withoutGlobalScopes()
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->limit(200)
            ->get();

        $count = 0;
        foreach ($dueSchedules as $schedule) {
            $schedule->last_run_at = now();
            $schedule->next_run_at = $scheduleService->prepareNextRun($schedule);
            $schedule->save();

            SendScheduledReportJob::dispatch($schedule->id);
            $count++;
        }

        $this->info("Dispatched {$count} scheduled report job(s).");

        return self::SUCCESS;
    }
}

