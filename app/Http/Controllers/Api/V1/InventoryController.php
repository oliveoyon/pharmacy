<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Inventory\FefoAllocator;
use App\Services\Inventory\InventoryLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryLedgerService $ledgerService,
        private readonly FefoAllocator $fefoAllocator,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function adjust(Request $request): JsonResponse
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
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'batch_id' => [
                'required',
                'integer',
                Rule::exists('batches', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'movement_type' => ['required', 'string', 'max:40'],
            'qty_change' => ['required', 'numeric', 'not_in:0'],
            'unit_cost' => ['nullable', 'numeric'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'moved_at' => ['nullable', 'date'],
        ]);

        $movement = $this->ledgerService->recordMovement([
            ...$validated,
            'organization_id' => $organizationId,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json($movement, 201);
    }

    public function fefoSuggestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('organization_id', $this->tenantContext->organizationId())),
            ],
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('organization_id', $this->tenantContext->organizationId())),
            ],
            'qty' => ['required', 'numeric', 'gt:0'],
        ]);

        $allocations = $this->fefoAllocator->suggest(
            (int) $validated['branch_id'],
            (int) $validated['product_id'],
            (float) $validated['qty']
        );

        return response()->json([
            'requested_qty' => (float) $validated['qty'],
            'allocated_qty' => $allocations->sum('allocated_qty'),
            'allocations' => $allocations,
        ]);
    }
}
