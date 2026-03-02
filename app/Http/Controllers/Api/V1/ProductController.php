<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext)
    {
    }

    public function index(): JsonResponse
    {
        $products = Product::query()
            ->with(['purchaseUnit:id,name,short_code', 'stockUnit:id,name,short_code', 'barcodes:id,product_id,barcode,is_primary'])
            ->orderBy('name')
            ->paginate(25);

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $organizationId = $this->tenantContext->organizationId();
        if (! $organizationId) {
            return response()->json(['message' => 'Organization context is required.'], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:80',
                Rule::unique('products')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'generic_name' => ['nullable', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:60'],
            'dosage_form' => ['nullable', 'string', 'max:60'],
            'manufacturer_name' => ['nullable', 'string', 'max:255'],
            'purchase_unit_id' => [
                'nullable',
                'integer',
                Rule::exists('units', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'stock_unit_id' => [
                'nullable',
                'integer',
                Rule::exists('units', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'purchase_to_stock_factor' => ['nullable', 'numeric', 'gt:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'mrp' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'is_controlled_drug' => ['nullable', 'boolean'],
            'requires_cold_chain' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'barcodes' => ['nullable', 'array'],
            'barcodes.*.barcode' => [
                'required',
                'string',
                'max:80',
                Rule::unique('product_barcodes', 'barcode')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'barcodes.*.is_primary' => ['nullable', 'boolean'],
        ]);

        $product = DB::transaction(function () use ($validated, $organizationId) {
            $product = Product::query()->create([
                ...collect($validated)->except('barcodes')->toArray(),
                'organization_id' => $organizationId,
                'purchase_to_stock_factor' => $validated['purchase_to_stock_factor'] ?? 1,
                'purchase_price' => $validated['purchase_price'] ?? 0,
                'mrp' => $validated['mrp'] ?? 0,
                'selling_price' => $validated['selling_price'] ?? 0,
                'tax_percent' => $validated['tax_percent'] ?? 0,
                'reorder_level' => $validated['reorder_level'] ?? 0,
                'is_controlled_drug' => $validated['is_controlled_drug'] ?? false,
                'requires_cold_chain' => $validated['requires_cold_chain'] ?? false,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            foreach ($validated['barcodes'] ?? [] as $barcodePayload) {
                ProductBarcode::query()->create([
                    'organization_id' => $organizationId,
                    'product_id' => $product->id,
                    'barcode' => $barcodePayload['barcode'],
                    'is_primary' => $barcodePayload['is_primary'] ?? false,
                ]);
            }

            return $product->load('barcodes:id,product_id,barcode,is_primary');
        });

        return response()->json($product, 201);
    }
}
