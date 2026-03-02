<?php

namespace App\Services\Sales;

use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Services\Inventory\InventoryLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReturnService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly InventoryLedgerService $inventoryLedgerService,
    ) {
    }

    public function post(array $payload, ?int $userId = null): SalesReturn
    {
        $organizationId = $this->tenantContext->organizationId();
        if (! $organizationId) {
            throw ValidationException::withMessages([
                'organization_id' => 'Organization context is required.',
            ]);
        }

        return DB::transaction(function () use ($payload, $organizationId, $userId) {
            $invoice = \App\Models\SalesInvoice::query()->findOrFail($payload['sales_invoice_id']);

            $returnNo = $payload['return_no'] ?? $this->nextReturnNo($organizationId);
            $salesReturn = SalesReturn::query()->create([
                'organization_id' => $organizationId,
                'sales_invoice_id' => $invoice->id,
                'return_no' => $returnNo,
                'status' => 'posted',
                'reason' => $payload['reason'] ?? null,
                'returned_at' => $payload['returned_at'] ?? now(),
                'created_by' => $userId,
                'metadata' => $payload['metadata'] ?? null,
            ]);

            $total = 0.0;
            foreach ($payload['items'] as $itemIndex => $itemPayload) {
                $invoiceItem = SalesInvoiceItem::query()->findOrFail($itemPayload['sales_invoice_item_id']);
                if ((int) $invoiceItem->sales_invoice_id !== (int) $invoice->id) {
                    throw ValidationException::withMessages([
                        "items.$itemIndex.sales_invoice_item_id" => 'Invoice item does not belong to the given invoice.',
                    ]);
                }

                $alreadyReturned = (float) SalesReturnItem::query()
                    ->where('sales_invoice_item_id', $invoiceItem->id)
                    ->sum('return_qty');
                $availableToReturn = (float) $invoiceItem->sold_qty - $alreadyReturned;
                $requestedReturn = (float) $itemPayload['return_qty'];

                if ($requestedReturn <= 0 || $requestedReturn > $availableToReturn) {
                    throw ValidationException::withMessages([
                        "items.$itemIndex.return_qty" => 'Invalid return quantity.',
                    ]);
                }

                $lineTotal = $requestedReturn * (float) $invoiceItem->unit_price;

                SalesReturnItem::query()->create([
                    'organization_id' => $organizationId,
                    'sales_return_id' => $salesReturn->id,
                    'sales_invoice_item_id' => $invoiceItem->id,
                    'product_id' => $invoiceItem->product_id,
                    'batch_id' => $invoiceItem->batch_id,
                    'return_qty' => $requestedReturn,
                    'unit_price' => $invoiceItem->unit_price,
                    'line_total' => $lineTotal,
                    'reason' => $itemPayload['reason'] ?? $payload['reason'] ?? null,
                    'metadata' => $itemPayload['metadata'] ?? null,
                ]);

                $this->inventoryLedgerService->recordMovement([
                    'organization_id' => $organizationId,
                    'branch_id' => $invoice->branch_id,
                    'product_id' => $invoiceItem->product_id,
                    'batch_id' => $invoiceItem->batch_id,
                    'created_by' => $userId,
                    'movement_type' => 'SALE_RETURN_IN',
                    'qty_change' => $requestedReturn,
                    'unit_cost' => (float) $invoiceItem->cost_at_sale,
                    'reference_type' => SalesReturn::class,
                    'reference_id' => $salesReturn->id,
                    'notes' => $payload['reason'] ?? null,
                    'metadata' => ['return_no' => $returnNo],
                    'moved_at' => $payload['returned_at'] ?? now(),
                ]);

                $total += $lineTotal;
            }

            $salesReturn->return_total = $total;
            $salesReturn->save();

            $invoiceItemCount = (int) $invoice->items()->count();
            $fullyReturnedCount = (int) $invoice->items()
                ->whereRaw('sold_qty <= (SELECT COALESCE(SUM(return_qty),0) FROM sales_return_items WHERE sales_return_items.sales_invoice_item_id = sales_invoice_items.id)')
                ->count();
            $invoice->status = ($invoiceItemCount > 0 && $invoiceItemCount === $fullyReturnedCount) ? 'returned' : 'partial_returned';
            $invoice->save();

            return $salesReturn->load(['items', 'invoice']);
        });
    }

    private function nextReturnNo(int $organizationId): string
    {
        $prefix = 'RET-'.now()->format('Ym').'-';

        $last = SalesReturn::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('return_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('return_no');

        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $matches) === 1) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}

