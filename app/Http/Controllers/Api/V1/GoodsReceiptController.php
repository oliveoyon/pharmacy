<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Services\Purchase\GoodsReceiptPostingService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly GoodsReceiptPostingService $postingService,
    ) {
    }

    public function index(): JsonResponse
    {
        $receipts = GoodsReceipt::query()
            ->with(['supplier:id,name', 'branch:id,name'])
            ->latest('id')
            ->paginate(25);

        return response()->json($receipts);
    }

    public function store(Request $request): JsonResponse
    {
        $organizationId = $this->tenantContext->organizationId();
        if (! $organizationId) {
            return response()->json(['message' => 'Organization context is required.'], 422);
        }

        $validated = $request->validate([
            'grn_no' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('goods_receipts')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'idempotency_key' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('goods_receipts')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'purchase_order_id' => [
                'nullable',
                'integer',
                Rule::exists('purchase_orders', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'supplier_invoice_no' => ['nullable', 'string', 'max:80'],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => [
                'nullable',
                'integer',
                Rule::exists('purchase_order_items', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'items.*.batch_no' => ['required', 'string', 'max:80'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.received_qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.mrp' => ['nullable', 'numeric', 'min:0'],
            'items.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.metadata' => ['nullable', 'array'],
        ]);

        $receipt = $this->postingService->post($validated, $request->user()?->id);

        return response()->json($receipt, 201);
    }
}
