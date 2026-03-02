<?php

namespace App\Services\Purchase;

use App\Models\Batch;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\Inventory\InventoryLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceiptPostingService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InventoryLedgerService $inventoryLedgerService,
    ) {
    }

    public function post(array $payload, ?int $userId = null): GoodsReceipt
    {
        $organizationId = $this->tenantContext->organizationId();
        if (! $organizationId) {
            throw ValidationException::withMessages([
                'organization_id' => 'Organization context is required.',
            ]);
        }

        return DB::transaction(function () use ($payload, $organizationId, $userId) {
            if (! empty($payload['idempotency_key'])) {
                $existing = GoodsReceipt::query()
                    ->where('organization_id', $organizationId)
                    ->where('idempotency_key', $payload['idempotency_key'])
                    ->first();
                if ($existing) {
                    return $existing->load(['items', 'supplier', 'branch', 'purchaseOrder']);
                }
            }

            $purchaseOrder = null;
            if (! empty($payload['purchase_order_id'])) {
                $purchaseOrder = PurchaseOrder::query()
                    ->whereKey($payload['purchase_order_id'])
                    ->firstOrFail();
            }

            $grnNo = $payload['grn_no'] ?? $this->nextGrnNo($organizationId);

            $receipt = GoodsReceipt::query()->create([
                'organization_id' => $organizationId,
                'branch_id' => $payload['branch_id'],
                'supplier_id' => $payload['supplier_id'],
                'purchase_order_id' => $purchaseOrder?->id,
                'grn_no' => $grnNo,
                'idempotency_key' => $payload['idempotency_key'] ?? null,
                'supplier_invoice_no' => $payload['supplier_invoice_no'] ?? null,
                'status' => 'posted',
                'received_at' => $payload['received_at'] ?? now(),
                'created_by' => $userId,
                'notes' => $payload['notes'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
            ]);

            $subTotal = 0.0;
            $taxTotal = 0.0;
            $discountTotal = 0.0;

            foreach ($payload['items'] as $itemPayload) {
                $product = \App\Models\Product::query()->whereKey($itemPayload['product_id'])->firstOrFail();

                $batch = Batch::query()->firstOrCreate(
                    [
                        'organization_id' => $organizationId,
                        'product_id' => $product->id,
                        'batch_no' => $itemPayload['batch_no'],
                        'expiry_date' => $itemPayload['expiry_date'] ?? null,
                    ],
                    [
                        'received_at' => now()->toDateString(),
                        'cost_price' => $itemPayload['unit_cost'] ?? $product->purchase_price,
                        'mrp' => $itemPayload['mrp'] ?? $product->mrp,
                        'selling_price' => $itemPayload['selling_price'] ?? $product->selling_price,
                        'status' => 'active',
                    ]
                );

                $qty = (float) $itemPayload['received_qty'];
                $unitCost = (float) ($itemPayload['unit_cost'] ?? $batch->cost_price ?? 0);
                $taxPercent = (float) ($itemPayload['tax_percent'] ?? 0);
                $discountAmount = (float) ($itemPayload['discount_amount'] ?? 0);
                $lineBase = $qty * $unitCost;
                $lineTax = ($lineBase * $taxPercent) / 100;
                $lineTotal = $lineBase + $lineTax - $discountAmount;

                $purchaseOrderItemId = null;
                if (! empty($itemPayload['purchase_order_item_id']) && $purchaseOrder) {
                    $poItem = PurchaseOrderItem::query()
                        ->where('purchase_order_id', $purchaseOrder->id)
                        ->whereKey($itemPayload['purchase_order_item_id'])
                        ->first();

                    if ($poItem) {
                        $poItem->received_qty = (float) $poItem->received_qty + $qty;
                        $poItem->save();
                        $purchaseOrderItemId = $poItem->id;
                    }
                }

                GoodsReceiptItem::query()->create([
                    'organization_id' => $organizationId,
                    'goods_receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $purchaseOrderItemId,
                    'product_id' => $product->id,
                    'batch_id' => $batch->id,
                    'batch_no' => $batch->batch_no,
                    'expiry_date' => $batch->expiry_date,
                    'received_qty' => $qty,
                    'unit_cost' => $unitCost,
                    'tax_percent' => $taxPercent,
                    'discount_amount' => $discountAmount,
                    'line_total' => $lineTotal,
                    'metadata' => $itemPayload['metadata'] ?? null,
                ]);

                $this->inventoryLedgerService->recordMovement([
                    'organization_id' => $organizationId,
                    'branch_id' => $payload['branch_id'],
                    'product_id' => $product->id,
                    'batch_id' => $batch->id,
                    'created_by' => $userId,
                    'movement_type' => 'GRN_IN',
                    'qty_change' => $qty,
                    'unit_cost' => $unitCost,
                    'reference_type' => GoodsReceipt::class,
                    'reference_id' => $receipt->id,
                    'notes' => $payload['notes'] ?? null,
                    'metadata' => ['grn_no' => $grnNo],
                    'moved_at' => $payload['received_at'] ?? now(),
                ]);

                $subTotal += $lineBase;
                $taxTotal += $lineTax;
                $discountTotal += $discountAmount;
            }

            $receipt->sub_total = $subTotal;
            $receipt->tax_total = $taxTotal;
            $receipt->discount_total = $discountTotal;
            $receipt->grand_total = $subTotal + $taxTotal - $discountTotal;
            $receipt->save();

            if ($purchaseOrder) {
                $allReceived = $purchaseOrder->items()
                    ->whereColumn('received_qty', '<', 'ordered_qty')
                    ->doesntExist();
                $purchaseOrder->status = $allReceived ? 'received' : 'partial_received';
                $purchaseOrder->save();
            }

            return $receipt->load(['items', 'supplier', 'branch', 'purchaseOrder']);
        });
    }

    private function nextGrnNo(int $organizationId): string
    {
        $prefix = 'GRN-'.now()->format('Ym').'-';

        $last = GoodsReceipt::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('grn_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('grn_no');

        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $matches) === 1) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
