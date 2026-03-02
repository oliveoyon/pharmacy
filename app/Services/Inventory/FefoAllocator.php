<?php

namespace App\Services\Inventory;

use App\Models\StockBalance;
use Illuminate\Support\Collection;

class FefoAllocator
{
    public function suggest(int $branchId, int $productId, float $requestedQty, bool $lockForUpdate = false): Collection
    {
        if ($requestedQty <= 0) {
            return collect();
        }

        $query = StockBalance::query()
            ->with('batch:id,expiry_date,batch_no')
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('qty', '>', 0)
            ->orderByRaw("COALESCE((SELECT expiry_date FROM batches WHERE batches.id = stock_balances.batch_id), '9999-12-31') asc");

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $balances = $query->get();

        $remaining = $requestedQty;
        $allocations = collect();

        foreach ($balances as $balance) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float) $balance->qty - (float) $balance->reserved_qty;
            if ($available <= 0) {
                continue;
            }

            $allocated = min($available, $remaining);
            $allocations->push([
                'batch_id' => $balance->batch_id,
                'batch_no' => $balance->batch?->batch_no,
                'expiry_date' => optional($balance->batch?->expiry_date)->format('Y-m-d'),
                'available_qty' => $available,
                'allocated_qty' => $allocated,
            ]);

            $remaining -= $allocated;
        }

        return $allocations;
    }
}
