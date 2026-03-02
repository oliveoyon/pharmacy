<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SendScheduledReportJob;
use App\Models\ReportSchedule;
use App\Services\Reports\ReportScheduleService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportScheduleController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ReportScheduleService $scheduleService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json(
            ReportSchedule::query()->latest('id')->paginate(25)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $organizationId = $this->tenantContext->organizationId();
        if (! $organizationId) {
            return response()->json(['message' => 'Organization context is required.'], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'report_type' => ['required', 'in:sales_summary,stock_valuation,expiry_alerts'],
            'frequency' => ['required', 'in:daily,weekly,monthly'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['required', 'email'],
            'filters' => ['nullable', 'array'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $timezone = $validated['timezone'] ?? 'UTC';
        $schedule = ReportSchedule::query()->create([
            'organization_id' => $organizationId,
            'name' => $validated['name'],
            'report_type' => $validated['report_type'],
            'frequency' => $validated['frequency'],
            'recipients' => array_values(array_unique($validated['recipients'])),
            'filters' => $validated['filters'] ?? null,
            'timezone' => $timezone,
            'is_active' => $validated['is_active'] ?? true,
            'next_run_at' => $this->scheduleService->nextRunAt($validated['frequency'], $timezone),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json($schedule, 201);
    }

    public function update(Request $request, ReportSchedule $reportSchedule): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'report_type' => ['sometimes', 'in:sales_summary,stock_valuation,expiry_alerts'],
            'frequency' => ['sometimes', 'in:daily,weekly,monthly'],
            'recipients' => ['sometimes', 'array', 'min:1'],
            'recipients.*' => ['required_with:recipients', 'email'],
            'filters' => ['nullable', 'array'],
            'timezone' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $reportSchedule->fill($validated);
        if (isset($validated['recipients'])) {
            $reportSchedule->recipients = array_values(array_unique($validated['recipients']));
        }

        if (isset($validated['frequency']) || isset($validated['timezone'])) {
            $reportSchedule->next_run_at = $this->scheduleService->nextRunAt(
                $reportSchedule->frequency,
                $reportSchedule->timezone
            );
        }

        $reportSchedule->save();

        return response()->json($reportSchedule);
    }

    public function runNow(ReportSchedule $reportSchedule): JsonResponse
    {
        SendScheduledReportJob::dispatch($reportSchedule->id);

        return response()->json([
            'message' => 'Scheduled report queued.',
            'report_schedule_id' => $reportSchedule->id,
        ]);
    }
}

