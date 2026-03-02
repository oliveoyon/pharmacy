<?php

namespace App\Services\Inventory;

use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryLedgerService
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function recordMovement(array $data): StockMovement
    {
        $organizationId = $data['organization_id'] ?? $this->tenantContext->organizationId();
        if (! $organizationId) {
            throw ValidationException::withMessages([
                'organization_id' => 'Organization context is required.',
            ]);
        }

        $qtyChange = (float) ($data['qty_change'] ?? 0);
        if ($qtyChange == 0.0) {
            throw ValidationException::withMessages([
                'qty_change' => 'Quantity change must be non-zero.',
            ]);
        }

        return DB::transaction(function () use ($data, $organizationId, $qtyChange) {
            $balance = StockBalance::query()
                ->where('organization_id', $organizationId)
                ->where('branch_id', $data['branch_id'])
                ->where('product_id', $data['product_id'])
                ->where('batch_id', $data['batch_id'])
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = new StockBalance([
                    'organization_id' => $organizationId,
                    'branch_id' => $data['branch_id'],
                    'product_id' => $data['product_id'],
                    'batch_id' => $data['batch_id'],
                    'qty' => 0,
                    'reserved_qty' => 0,
                ]);
            }

            $currentQty = (float) $balance->qty;
            $newQty = $currentQty + $qtyChange;
            if ($newQty < 0) {
                throw ValidationException::withMessages([
                    'qty_change' => 'Insufficient stock for this movement.',
                ]);
            }

            $balance->qty = $newQty;
            $balance->last_movement_at = now();
            $balance->save();

            $unitCost = isset($data['unit_cost']) ? (float) $data['unit_cost'] : null;
            $movement = StockMovement::query()->create([
                'organization_id' => $organizationId,
                'branch_id' => $data['branch_id'],
                'product_id' => $data['product_id'],
                'batch_id' => $data['batch_id'],
                'created_by' => $data['created_by'] ?? null,
                'movement_type' => $data['movement_type'],
                'qty_change' => $qtyChange,
                'unit_cost' => $unitCost,
                'total_cost' => $unitCost !== null ? $unitCost * $qtyChange : null,
                'balance_after' => $newQty,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'moved_at' => $data['moved_at'] ?? now(),
            ]);

            return $movement;
        });
    }
}

