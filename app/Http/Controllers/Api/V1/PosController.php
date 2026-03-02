<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SalesInvoice;
use App\Services\Sales\PosSaleService;
use App\Services\Sales\SalesReturnService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PosSaleService $posSaleService,
        private readonly SalesReturnService $salesReturnService,
    ) {
    }

    public function invoices(Request $request): JsonResponse
    {
        $query = SalesInvoice::query()
            ->with(['items.product:id,name,sku', 'payments'])
            ->latest('id');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        return response()->json($query->paginate(25));
    }

    public function sell(Request $request): JsonResponse
    {
        $organizationId = $this->tenantContext->organizationId();
        if (! $organizationId) {
            return response()->json(['message' => 'Organization context is required.'], 422);
        }

        $validated = $request->validate([
            'invoice_no' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('sales_invoices')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'idempotency_key' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('sales_invoices')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'counter_session_id' => [
                'nullable',
                'integer',
                Rule::exists('counter_sessions', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)->where('status', 'open')),
            ],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'invoice_type' => ['nullable', 'in:retail_cash,retail_credit,wholesale'],
            'sold_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'items.*.batch_id' => [
                'nullable',
                'integer',
                Rule::exists('batches', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['nullable', 'array'],
            'payments.*.payment_method' => ['required_with:payments', 'string', 'max:30'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'gt:0'],
            'payments.*.transaction_ref' => ['nullable', 'string', 'max:120'],
            'payments.*.paid_at' => ['nullable', 'date'],
            'payments.*.metadata' => ['nullable', 'array'],
        ]);

        $invoice = $this->posSaleService->post($validated, $request->user()?->id);

        return response()->json($invoice, 201);
    }

    public function salesReturn(Request $request): JsonResponse
    {
        $organizationId = $this->tenantContext->organizationId();
        if (! $organizationId) {
            return response()->json(['message' => 'Organization context is required.'], 422);
        }

        $validated = $request->validate([
            'sales_invoice_id' => [
                'required',
                'integer',
                Rule::exists('sales_invoices', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'return_no' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('sales_returns')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'reason' => ['nullable', 'string'],
            'returned_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sales_invoice_item_id' => [
                'required',
                'integer',
                Rule::exists('sales_invoice_items', 'id')->where(fn ($q) => $q->where('organization_id', $organizationId)),
            ],
            'items.*.return_qty' => ['required', 'numeric', 'gt:0'],
            'items.*.reason' => ['nullable', 'string'],
            'items.*.metadata' => ['nullable', 'array'],
        ]);

        $salesReturn = $this->salesReturnService->post($validated, $request->user()?->id);

        return response()->json($salesReturn, 201);
    }
}
