<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Purchase\GoodsReceiptPostingService;
use App\Services\Sales\PosSaleService;
use App\Services\Sales\SalesReturnService;
use App\Support\Tenancy\TenantContext;
use Tests\TestCase;

class StockFlowIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Branch $branch;

    private User $user;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->organization = Organization::query()->create([
            'name' => 'Stock Tenant',
            'code' => 'stock-tenant',
        ]);

        $this->branch = Branch::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'type' => 'pharmacy',
        ]);

        $this->user = User::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Owner',
            'email' => 'stock-owner@example.com',
            'password' => 'password',
            'is_organization_admin' => true,
        ]);
        $this->user->assignRole('org_owner');

        $this->supplier = Supplier::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Demo Supplier',
            'code' => 'SUP-1',
            'is_active' => true,
        ]);

        $unit = Unit::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Piece',
            'short_code' => 'pcs',
            'is_base' => true,
            'is_active' => true,
        ]);

        $this->product = Product::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Paracetamol 500',
            'sku' => 'PCM500',
            'purchase_unit_id' => $unit->id,
            'stock_unit_id' => $unit->id,
            'purchase_to_stock_factor' => 1,
            'purchase_price' => 5,
            'mrp' => 8,
            'selling_price' => 7,
            'tax_percent' => 0,
            'is_active' => true,
        ]);

        app(TenantContext::class)->setOrganizationId($this->organization->id);
        app(TenantContext::class)->setBranchId($this->branch->id);
    }

    public function test_grn_posting_updates_stock_balance_and_creates_grn_in_ledger(): void
    {
        $receipt = app(GoodsReceiptPostingService::class)->post([
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'batch_no' => 'BATCH-A',
                    'expiry_date' => '2027-01-31',
                    'received_qty' => 10,
                    'unit_cost' => 5,
                ],
            ],
        ], $this->user->id);

        $this->assertInstanceOf(GoodsReceipt::class, $receipt);

        $batch = Batch::query()->where('batch_no', 'BATCH-A')->first();
        $this->assertNotNull($batch);

        $balance = StockBalance::query()
            ->where('branch_id', $this->branch->id)
            ->where('product_id', $this->product->id)
            ->where('batch_id', $batch->id)
            ->first();

        $this->assertNotNull($balance);
        $this->assertEquals(10.0, (float) $balance->qty);

        $movement = StockMovement::query()
            ->where('branch_id', $this->branch->id)
            ->where('product_id', $this->product->id)
            ->where('batch_id', $batch->id)
            ->where('movement_type', 'GRN_IN')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(10.0, (float) $movement->qty_change);
    }

    public function test_pos_sale_uses_fefo_and_reduces_older_batch_first(): void
    {
        $this->createGrn('BATCH-OLD', '2026-06-30', 5, 5);
        $this->createGrn('BATCH-NEW', '2027-12-31', 10, 5);

        $invoice = app(PosSaleService::class)->post([
            'branch_id' => $this->branch->id,
            'invoice_type' => 'retail_cash',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 12,
                    'unit_price' => 7,
                ],
            ],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 84],
            ],
        ], $this->user->id);

        $invoiceId = (int) $invoice->id;
        $this->assertGreaterThan(0, $invoiceId);
        $this->assertCount(2, $invoice->items);

        $oldBatch = Batch::query()->where('batch_no', 'BATCH-OLD')->firstOrFail();
        $newBatch = Batch::query()->where('batch_no', 'BATCH-NEW')->firstOrFail();

        $oldBalance = StockBalance::query()->where('branch_id', $this->branch->id)->where('batch_id', $oldBatch->id)->firstOrFail();
        $newBalance = StockBalance::query()->where('branch_id', $this->branch->id)->where('batch_id', $newBatch->id)->firstOrFail();

        $this->assertEquals(0.0, (float) $oldBalance->qty);
        $this->assertEquals(3.0, (float) $newBalance->qty);

        $saleOutCount = StockMovement::query()
            ->where('reference_type', \App\Models\SalesInvoice::class)
            ->where('reference_id', $invoiceId)
            ->where('movement_type', 'SALE_OUT')
            ->count();
        $this->assertEquals(2, $saleOutCount);
    }

    public function test_sales_return_reintegrates_stock_and_rejects_over_return(): void
    {
        $this->createGrn('BATCH-R', '2027-05-30', 10, 5);

        $batch = Batch::query()->where('batch_no', 'BATCH-R')->firstOrFail();
        $sale = app(PosSaleService::class)->post([
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'batch_id' => $batch->id,
                    'qty' => 4,
                    'unit_price' => 7,
                ],
            ],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 28],
            ],
        ], $this->user->id);

        $invoiceId = (int) $sale->id;
        $invoiceItemId = (int) $sale->items->first()->id;

        $threw = false;
        try {
            app(SalesReturnService::class)->post([
                'sales_invoice_id' => $invoiceId,
                'reason' => 'Over return test',
                'items' => [
                    [
                        'sales_invoice_item_id' => $invoiceItemId,
                        'return_qty' => 5,
                    ],
                ],
            ], $this->user->id);
        } catch (\Illuminate\Validation\ValidationException) {
            $threw = true;
        }
        $this->assertTrue($threw);

        $salesReturn = app(SalesReturnService::class)->post([
            'sales_invoice_id' => $invoiceId,
            'reason' => 'Damaged strip',
            'items' => [
                [
                    'sales_invoice_item_id' => $invoiceItemId,
                    'return_qty' => 2,
                ],
            ],
        ], $this->user->id);
        $this->assertNotNull($salesReturn->id);

        $balance = StockBalance::query()
            ->where('branch_id', $this->branch->id)
            ->where('product_id', $this->product->id)
            ->where('batch_id', $batch->id)
            ->firstOrFail();

        $this->assertEquals(8.0, (float) $balance->qty);

        $returnMovement = StockMovement::query()
            ->where('movement_type', 'SALE_RETURN_IN')
            ->where('batch_id', $batch->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($returnMovement);
        $this->assertEquals(2.0, (float) $returnMovement->qty_change);
    }

    public function test_pos_sale_with_same_idempotency_key_does_not_duplicate_invoice_or_ledger(): void
    {
        $this->createGrn('BATCH-IDEM', '2027-08-31', 10, 5);

        $payload = [
            'branch_id' => $this->branch->id,
            'idempotency_key' => 'sale-idem-001',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 3,
                    'unit_price' => 7,
                ],
            ],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 21],
            ],
        ];

        $first = app(PosSaleService::class)->post($payload, $this->user->id);
        $second = app(PosSaleService::class)->post($payload, $this->user->id);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, SalesInvoice::query()->where('idempotency_key', 'sale-idem-001')->count());
        $this->assertEquals(
            1,
            StockMovement::query()
                ->where('reference_type', \App\Models\SalesInvoice::class)
                ->where('reference_id', $first->id)
                ->where('movement_type', 'SALE_OUT')
                ->count()
        );
    }

    public function test_grn_with_same_idempotency_key_does_not_duplicate_receipt_or_ledger(): void
    {
        $payload = [
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'idempotency_key' => 'grn-idem-001',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'batch_no' => 'BATCH-GID',
                    'expiry_date' => '2027-10-31',
                    'received_qty' => 4,
                    'unit_cost' => 5,
                ],
            ],
        ];

        $first = app(GoodsReceiptPostingService::class)->post($payload, $this->user->id);
        $second = app(GoodsReceiptPostingService::class)->post($payload, $this->user->id);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, GoodsReceipt::query()->where('idempotency_key', 'grn-idem-001')->count());
        $this->assertEquals(
            1,
            StockMovement::query()
                ->where('reference_type', \App\Models\GoodsReceipt::class)
                ->where('reference_id', $first->id)
                ->where('movement_type', 'GRN_IN')
                ->count()
        );
    }

    public function test_second_sale_fails_when_stock_already_exhausted(): void
    {
        $this->createGrn('BATCH-EXH', '2027-11-30', 5, 5);

        app(PosSaleService::class)->post([
            'branch_id' => $this->branch->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 5,
                    'unit_price' => 7,
                ],
            ],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 35],
            ],
        ], $this->user->id);

        $threw = false;
        try {
            app(PosSaleService::class)->post([
                'branch_id' => $this->branch->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'qty' => 1,
                        'unit_price' => 7,
                    ],
                ],
                'payments' => [
                    ['payment_method' => 'cash', 'amount' => 7],
                ],
            ], $this->user->id);
        } catch (\Illuminate\Validation\ValidationException) {
            $threw = true;
        }

        $this->assertTrue($threw);
    }

    private function createGrn(string $batchNo, string $expiryDate, float $qty, float $unitCost): void
    {
        $receipt = app(GoodsReceiptPostingService::class)->post([
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'batch_no' => $batchNo,
                    'expiry_date' => $expiryDate,
                    'received_qty' => $qty,
                    'unit_cost' => $unitCost,
                ],
            ],
        ], $this->user->id);

        $this->assertNotNull($receipt->id);
    }
}
