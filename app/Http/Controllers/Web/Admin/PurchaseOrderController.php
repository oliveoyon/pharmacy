<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $organizationId = $this->organizationId($request);

        $orders = PurchaseOrder::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->with(['supplier:id,name', 'branch:id,name', 'items.product:id,name'])
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

        $products = Product::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        return view('web.admin.purchase-orders.index', compact('orders', 'branches', 'suppliers', 'products'));
    }

    public function store(Request $request): RedirectResponse
    {
        $organizationId = $this->organizationId($request);

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'ordered_at' => ['nullable', 'date'],
            'expected_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.ordered_qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->ensureOwnedRecord($organizationId, 'branches', (int) $validated['branch_id']);
        $this->ensureOwnedRecord($organizationId, 'suppliers', (int) $validated['supplier_id']);

        foreach ($validated['items'] as $item) {
            $this->ensureOwnedRecord($organizationId, 'products', (int) $item['product_id']);
        }

        DB::transaction(function () use ($validated, $organizationId, $request): void {
            $poNo = $this->nextPoNo($organizationId);

            $order = PurchaseOrder::withoutGlobalScopes()->create([
                'organization_id' => $organizationId,
                'branch_id' => $validated['branch_id'],
                'supplier_id' => $validated['supplier_id'],
                'po_no' => $poNo,
                'status' => 'approved',
                'ordered_at' => $validated['ordered_at'] ?? now()->toDateString(),
                'expected_at' => $validated['expected_at'] ?? null,
                'created_by' => $request->user()?->id,
                'notes' => $validated['notes'] ?? null,
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

                PurchaseOrderItem::withoutGlobalScopes()->create([
                    'organization_id' => $organizationId,
                    'purchase_order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'ordered_qty' => $qty,
                    'unit_cost' => $unitCost,
                    'tax_percent' => $taxPercent,
                    'discount_amount' => $discount,
                    'line_total' => $lineTotal,
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
        });

        return back()->with('toast', [
            'type' => 'success',
            'title' => __('app.saved'),
            'text' => __('app.purchase_order_created'),
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

