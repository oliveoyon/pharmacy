<?php

namespace App\Services\Sales;

use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesPayment;
use App\Services\Inventory\FefoAllocator;
use App\Services\Inventory\InventoryLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosSaleService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly FefoAllocator $fefoAllocator,
        private readonly InventoryLedgerService $inventoryLedgerService,
    ) {
    }

    public function post(array $payload, ?int $userId = null): SalesInvoice
    {
        $organizationId = $this->tenantContext->organizationId();
        if (! $organizationId) {
            throw ValidationException::withMessages([
                'organization_id' => 'Organization context is required.',
            ]);
        }

        return DB::transaction(function () use ($payload, $organizationId, $userId) {
            if (! empty($payload['idempotency_key'])) {
                $existing = SalesInvoice::query()
                    ->where('organization_id', $organizationId)
                    ->where('idempotency_key', $payload['idempotency_key'])
                    ->first();
                if ($existing) {
                    return $existing->load(['items.product:id,name,sku', 'items.batch:id,batch_no,expiry_date', 'payments']);
                }
            }

            $invoiceNo = $payload['invoice_no'] ?? $this->nextInvoiceNo($organizationId);
            $invoice = SalesInvoice::query()->create([
                'organization_id' => $organizationId,
                'branch_id' => $payload['branch_id'],
                'counter_session_id' => $payload['counter_session_id'] ?? null,
                'customer_id' => $payload['customer_id'] ?? null,
                'invoice_no' => $invoiceNo,
                'idempotency_key' => $payload['idempotency_key'] ?? null,
                'invoice_type' => $payload['invoice_type'] ?? 'retail_cash',
                'status' => 'posted',
                'sold_at' => $payload['sold_at'] ?? now(),
                'created_by' => $userId,
                'notes' => $payload['notes'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
            ]);

            $subTotal = 0.0;
            $taxTotal = 0.0;
            $discountTotal = 0.0;

            foreach ($payload['items'] as $itemIndex => $item) {
                $product = Product::query()->findOrFail($item['product_id']);
                $requestedQty = (float) $item['qty'];
                $itemUnitPrice = (float) ($item['unit_price'] ?? $product->selling_price);
                $itemTaxPercent = (float) ($item['tax_percent'] ?? $product->tax_percent);
                $itemDiscount = (float) ($item['discount_amount'] ?? 0);

                $allocations = collect();
                if (! empty($item['batch_id'])) {
                    $allocations->push([
                        'batch_id' => (int) $item['batch_id'],
                        'allocated_qty' => $requestedQty,
                    ]);
                } else {
                    $allocations = $this->fefoAllocator->suggest(
                        (int) $payload['branch_id'],
                        (int) $product->id,
                        $requestedQty,
                        true
                    );
                }

                $allocatedQty = (float) $allocations->sum('allocated_qty');
                if ($allocatedQty < $requestedQty) {
                    throw ValidationException::withMessages([
                        "items.$itemIndex.qty" => 'Insufficient stock for FEFO allocation.',
                    ]);
                }

                foreach ($allocations as $allocation) {
                    $allocated = (float) $allocation['allocated_qty'];
                    $batch = \App\Models\Batch::query()->findOrFail($allocation['batch_id']);
                    $allocationRatio = $requestedQty > 0 ? ($allocated / $requestedQty) : 1;
                    $discountShare = $itemDiscount * $allocationRatio;
                    $lineBase = $allocated * $itemUnitPrice;
                    $lineTax = ($lineBase * $itemTaxPercent) / 100;
                    $lineTotal = $lineBase + $lineTax - $discountShare;

                    SalesInvoiceItem::query()->create([
                        'organization_id' => $organizationId,
                        'sales_invoice_id' => $invoice->id,
                        'product_id' => $product->id,
                        'batch_id' => $batch->id,
                        'sold_qty' => $allocated,
                        'unit_price' => $itemUnitPrice,
                        'cost_at_sale' => (float) $batch->cost_price,
                        'tax_percent' => $itemTaxPercent,
                        'discount_amount' => $discountShare,
                        'line_total' => $lineTotal,
                        'metadata' => ['source_item_index' => $itemIndex],
                    ]);

                    $this->inventoryLedgerService->recordMovement([
                        'organization_id' => $organizationId,
                        'branch_id' => $payload['branch_id'],
                        'product_id' => $product->id,
                        'batch_id' => $batch->id,
                        'created_by' => $userId,
                        'movement_type' => 'SALE_OUT',
                        'qty_change' => -1 * $allocated,
                        'unit_cost' => (float) $batch->cost_price,
                        'reference_type' => SalesInvoice::class,
                        'reference_id' => $invoice->id,
                        'notes' => $payload['notes'] ?? null,
                        'metadata' => ['invoice_no' => $invoiceNo],
                        'moved_at' => $payload['sold_at'] ?? now(),
                    ]);

                    $subTotal += $lineBase;
                    $taxTotal += $lineTax;
                    $discountTotal += $discountShare;
                }
            }

            $invoice->sub_total = $subTotal;
            $invoice->tax_total = $taxTotal;
            $invoice->discount_total = $discountTotal;
            $invoice->grand_total = $subTotal + $taxTotal - $discountTotal;

            $paidTotal = 0.0;
            foreach ($payload['payments'] ?? [] as $payment) {
                $amount = (float) $payment['amount'];
                if ($amount <= 0) {
                    continue;
                }

                SalesPayment::query()->create([
                    'organization_id' => $organizationId,
                    'sales_invoice_id' => $invoice->id,
                    'payment_method' => $payment['payment_method'],
                    'amount' => $amount,
                    'transaction_ref' => $payment['transaction_ref'] ?? null,
                    'paid_at' => $payment['paid_at'] ?? now(),
                    'received_by' => $userId,
                    'metadata' => $payment['metadata'] ?? null,
                ]);

                $paidTotal += $amount;
            }

            if ($paidTotal > (float) $invoice->grand_total) {
                throw ValidationException::withMessages([
                    'payments' => 'Total paid amount cannot exceed grand total.',
                ]);
            }

            $invoice->paid_total = $paidTotal;
            $invoice->due_total = (float) $invoice->grand_total - $paidTotal;
            $invoice->save();

            return $invoice->load(['items.product:id,name,sku', 'items.batch:id,batch_no,expiry_date', 'payments']);
        });
    }

    private function nextInvoiceNo(int $organizationId): string
    {
        $prefix = 'INV-'.now()->format('Ym').'-';

        $last = SalesInvoice::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('invoice_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('invoice_no');

        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $matches) === 1) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
