<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json(Unit::query()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $organizationId = $this->tenantContext->organizationId();
        if (! $organizationId) {
            return response()->json(['message' => 'Organization context is required.'], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('units')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'is_base' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $unit = Unit::query()->create([
            ...$validated,
            'organization_id' => $organizationId,
            'is_base' => $validated['is_base'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json($unit, 201);
    }
}

