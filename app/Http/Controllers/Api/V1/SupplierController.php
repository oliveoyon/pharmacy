<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json(Supplier::query()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $organizationId = $this->tenantContext->organizationId();
        if (! $organizationId) {
            return response()->json(['message' => 'Organization context is required.'], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('suppliers')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'due_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $supplier = Supplier::query()->create([
            ...$validated,
            'organization_id' => $organizationId,
            'credit_limit' => $validated['credit_limit'] ?? 0,
            'due_days' => $validated['due_days'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json($supplier, 201);
    }
}

