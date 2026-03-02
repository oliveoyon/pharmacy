<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function index(): JsonResponse
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier:id,name', 'branch:id,name'])
            ->latest('id')
            ->paginate(25);

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $organizationId = $this->tenantContext->organizationId();
        if (! $organizationId) {
            return response()->json(['message' => 'Organization context is required.'], 422);
        }

        $validated = $request->validate([
            'po_no' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('purchase_orders')->where(fn ($q) => $q->where('organization_id', $organizationId)),
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
            'ordered_at' => ['nullable', 'date'],
            'expected_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'items.*.ordered_qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $purchaseOrder = DB::transaction(function () use ($validated, $organizationId, $request) {
            $poNo = $validated['po_no'] ?? $this->nextPoNo($organizationId);

            $order = PurchaseOrder::query()->create([
                'organization_id' => $organizationId,
                'branch_id' => $validated['branch_id'],
                'supplier_id' => $validated['supplier_id'],
                'po_no' => $poNo,
                'status' => 'approved',
                'ordered_at' => $validated['ordered_at'] ?? now()->toDateString(),
                'expected_at' => $validated['expected_at'] ?? null,
                'created_by' => $request->user()?->id,
                'notes' => $validated['notes'] ?? null,
                'metadata' => $validated['metadata'] ?? null,
            ]);

            $subTotal = 0.0;
            $taxTotal = 0.0;
            $discountTotal = 0.0;

            foreach ($validated['items'] as $item) {
                $qty = (float) $item['ordered_qty'];
                $unitCost = (float) ($item['unit_cost'] ?? 0);
                $taxPercent = (float) ($item['tax_percent'] ?? 0);
                $discount = (float) ($item['discount_amount'] ?? 0);
                $lineBase = $qty * $unitCost;
                $lineTax = ($lineBase * $taxPercent) / 100;
                $lineTotal = $lineBase + $lineTax - $discount;

                PurchaseOrderItem::query()->create([
                    'organization_id' => $organizationId,
                    'purchase_order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'ordered_qty' => $qty,
                    'unit_cost' => $unitCost,
                    'tax_percent' => $taxPercent,
                    'discount_amount' => $discount,
                    'line_total' => $lineTotal,
                    'notes' => $item['notes'] ?? null,
                ]);

                $subTotal += $lineBase;
                $taxTotal += $lineTax;
                $discountTotal += $discount;
            }

            $order->sub_total = $subTotal;
            $order->tax_total = $taxTotal;
            $order->discount_total = $discountTotal;
            $order->grand_total = $subTotal + $taxTotal - $discountTotal;
            $order->save();

            return $order->load(['items.product:id,name,sku', 'supplier:id,name', 'branch:id,name']);
        });

        return response()->json($purchaseOrder, 201);
    }

    public function approve(PurchaseOrder $purchaseOrder, Request $request): JsonResponse
    {
        $purchaseOrder->status = 'approved';
        $purchaseOrder->approved_by = $request->user()?->id;
        $purchaseOrder->approved_at = now();
        $purchaseOrder->save();

        return response()->json([
            'message' => 'Purchase order approved.',
            'purchase_order' => $purchaseOrder,
        ]);
    }

    private function nextPoNo(int $organizationId): string
    {
        $prefix = 'PO-'.now()->format('Ym').'-';

        $last = PurchaseOrder::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('po_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('po_no');

        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $matches) === 1) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}

