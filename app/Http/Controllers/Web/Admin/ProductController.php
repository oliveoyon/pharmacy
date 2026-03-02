<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $organizationId = $this->organizationId($request);

        $products = Product::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->with(['purchaseUnit:id,name,short_code', 'stockUnit:id,name,short_code'])
            ->orderBy('name')
            ->paginate(15);

        $units = Unit::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get(['id', 'name', 'short_code']);

        return view('web.admin.products.index', compact('products', 'units'));
    }

    public function store(Request $request): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:80'],
            'generic_name' => ['nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:60'],
            'dosage_form' => ['nullable', 'string', 'max:60'],
            'manufacturer_name' => ['nullable', 'string', 'max:255'],
            'purchase_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'stock_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'mrp' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'is_controlled_drug' => ['nullable', 'boolean'],
            'requires_cold_chain' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Product::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            ...$validated,
            'purchase_to_stock_factor' => 1,
            'purchase_price' => $validated['purchase_price'] ?? 0,
            'mrp' => $validated['mrp'] ?? 0,
            'selling_price' => $validated['selling_price'] ?? 0,
            'tax_percent' => $validated['tax_percent'] ?? 0,
            'reorder_level' => $validated['reorder_level'] ?? 0,
            'is_controlled_drug' => (bool) ($validated['is_controlled_drug'] ?? false),
            'requires_cold_chain' => (bool) ($validated['requires_cold_chain'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'title' => __('app.saved'),
            'text' => __('app.product_created'),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->ensureOwned($request, $product->organization_id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:80'],
            'generic_name' => ['nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:60'],
            'dosage_form' => ['nullable', 'string', 'max:60'],
            'manufacturer_name' => ['nullable', 'string', 'max:255'],
            'purchase_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'stock_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'mrp' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'is_controlled_drug' => ['nullable', 'boolean'],
            'requires_cold_chain' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $product->update([
            ...$validated,
            'purchase_price' => $validated['purchase_price'] ?? 0,
            'mrp' => $validated['mrp'] ?? 0,
            'selling_price' => $validated['selling_price'] ?? 0,
            'tax_percent' => $validated['tax_percent'] ?? 0,
            'reorder_level' => $validated['reorder_level'] ?? 0,
            'is_controlled_drug' => (bool) ($validated['is_controlled_drug'] ?? false),
            'requires_cold_chain' => (bool) ($validated['requires_cold_chain'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'title' => __('app.updated'),
            'text' => __('app.product_updated'),
        ]);
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->ensureOwned($request, $product->organization_id);
        $product->delete();

        return back()->with('toast', [
            'type' => 'success',
            'title' => __('app.deleted'),
            'text' => __('app.product_deleted'),
        ]);
    }

    private function organizationId(Request $request): int
    {
        $organizationId = (int) ($request->user()?->organization_id ?? 0);
        abort_if($organizationId <= 0, 403, 'Organization context required.');

        return $organizationId;
    }

    private function ensureOwned(Request $request, int $organizationId): void
    {
        abort_if($organizationId !== $this->organizationId($request), 403);
    }
}

