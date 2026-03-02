<?php

namespace App\Services\Reports;

use App\Models\ReportSchedule;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ReportScheduleService
{
    public function nextRunAt(string $frequency, string $timezone, ?Carbon $from = null): Carbon
    {
        try {
            $tz = new DateTimeZone($timezone);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'timezone' => 'Invalid timezone.',
            ]);
        }

        $base = ($from ?? now())->copy()->setTimezone($tz);

        $next = match ($frequency) {
            'daily' => CarbonImmutable::instance($base)->addDay()->setTime(6, 0, 0),
            'weekly' => CarbonImmutable::instance($base)->next('monday')->setTime(6, 0, 0),
            'monthly' => CarbonImmutable::instance($base)->addMonthNoOverflow()->startOfMonth()->setTime(6, 0, 0),
            default => throw ValidationException::withMessages([
                'frequency' => 'Unsupported frequency.',
            ]),
        };

        return Carbon::instance($next)->setTimezone('UTC');
    }

    public function prepareNextRun(ReportSchedule $schedule): Carbon
    {
        $from = $schedule->next_run_at ?? now();
        return $this->nextRunAt($schedule->frequency, $schedule->timezone, $from);
    }
}

