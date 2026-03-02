<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CounterSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CounterSessionController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function open(Request $request): JsonResponse
    {
        $organizationId = $this->tenantContext->organizationId();
        if (! $organizationId) {
            return response()->json(['message' => 'Organization context is required.'], 422);
        }

        $validated = $request->validate([
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'counter_code' => ['nullable', 'string', 'max:40'],
            'opening_cash' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $openSession = CounterSession::query()
            ->where('branch_id', $validated['branch_id'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->first();
        if ($openSession) {
            return response()->json(['message' => 'An open counter session already exists for this user and branch.'], 422);
        }

        $session = CounterSession::query()->create([
            'organization_id' => $organizationId,
            'branch_id' => $validated['branch_id'],
            'user_id' => $request->user()->id,
            'counter_code' => $validated['counter_code'] ?? null,
            'status' => 'open',
            'opening_cash' => $validated['opening_cash'] ?? 0,
            'notes' => $validated['notes'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        return response()->json($session, 201);
    }

    public function close(CounterSession $counterSession, Request $request): JsonResponse
    {
        if ($counterSession->status !== 'open') {
            return response()->json(['message' => 'Counter session is already closed.'], 422);
        }

        if ((int) $counterSession->user_id !== (int) $request->user()->id && ! $request->user()->is_organization_admin) {
            return response()->json(['message' => 'Only owner or org admin can close this session.'], 403);
        }

        $validated = $request->validate([
            'closing_cash' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $counterSession->status = 'closed';
        $counterSession->closing_cash = $validated['closing_cash'] ?? null;
        $counterSession->closed_at = now();
        if (! empty($validated['notes'])) {
            $counterSession->notes = trim(($counterSession->notes ? $counterSession->notes."\n" : '').$validated['notes']);
        }
        $counterSession->save();

        return response()->json([
            'message' => 'Counter session closed.',
            'counter_session' => $counterSession,
        ]);
    }
}

