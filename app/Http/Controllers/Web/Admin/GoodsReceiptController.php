<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Purchase\GoodsReceiptPostingService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GoodsReceiptPostingService $postingService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function index(Request $request): View
    {
        $organizationId = $this->organizationId($request);

        $receipts = GoodsReceipt::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->with(['supplier:id,name', 'branch:id,name', 'purchaseOrder:id,po_no', 'items.product:id,name'])
            ->latest('id')
            ->paginate(12);

        $branches = Branch::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $suppliers = Supplier::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $purchaseOrders = PurchaseOrder::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['approved', 'partial_received'])
            ->latest('id')
            ->get(['id', 'po_no']);

        $products = Product::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        return view('web.admin.goods-receipts.index', compact('receipts', 'branches', 'suppliers', 'purchaseOrders', 'products'));
    }

    public function store(Request $request): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:80'],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.batch_no' => ['required', 'string', 'max:80'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.received_qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.mrp' => ['nullable', 'numeric', 'min:0'],
            'items.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->ensureOwnedRecord($organizationId, 'branches', (int) $validated['branch_id']);
        $this->ensureOwnedRecord($organizationId, 'suppliers', (int) $validated['supplier_id']);
        if (! empty($validated['purchase_order_id'])) {
            $this->ensureOwnedRecord($organizationId, 'purchase_orders', (int) $validated['purchase_order_id']);
        }
        foreach ($validated['items'] as $item) {
            $this->ensureOwnedRecord($organizationId, 'products', (int) $item['product_id']);
        }

        $this->tenantContext->setOrganizationId($organizationId);
        $this->tenantContext->setBranchId((int) $validated['branch_id']);

        $this->postingService->post([
            ...$validated,
            'idempotency_key' => 'web-grn-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4)),
        ], $request->user()?->id);

        return back()->with('toast', [
            'type' => 'success',
            'title' => __('app.saved'),
            'text' => __('app.grn_created'),
        ]);
    }

    private function organizationId(Request $request): int
    {
        $organizationId = (int) ($request->user()?->organization_id ?? 0);
        abort_if($organizationId <= 0, 403, 'Organization context required.');

        return $organizationId;
    }

    private function ensureOwnedRecord(int $organizationId, string $table, int $id): void
    {
        $exists = DB::table($table)
            ->where('id', $id)
            ->where('organization_id', $organizationId)
            ->exists();
        abort_if(! $exists, 403, 'Cross-tenant reference blocked.');
    }
}

