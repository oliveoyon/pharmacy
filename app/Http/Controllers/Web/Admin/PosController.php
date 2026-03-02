<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\CounterSession;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Services\Sales\PosSaleService;
use App\Services\Sales\SalesReturnService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        private readonly PosSaleService $posSaleService,
        private readonly SalesReturnService $salesReturnService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function index(Request $request): View
    {
        $organizationId = $this->organizationId($request);

        $branches = Branch::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $products = Product::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'selling_price']);

        $openSessions = CounterSession::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('status', 'open')
            ->latest('id')
            ->get(['id', 'branch_id', 'user_id', 'counter_code', 'opening_cash', 'opened_at']);

        $recentInvoices = SalesInvoice::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->with([
                'items' => function ($query): void {
                    $query->select('id', 'sales_invoice_id', 'product_id', 'batch_id', 'sold_qty', 'unit_price')
                        ->with('product:id,name')
                        ->withSum('returnItems', 'return_qty');
                },
            ])
            ->latest('id')
            ->limit(20)
            ->get(['id', 'invoice_no', 'branch_id', 'counter_session_id', 'grand_total', 'paid_total', 'sold_at']);

        return view('web.admin.pos.index', compact('branches', 'products', 'openSessions', 'recentInvoices'));
    }

    public function openCounterSession(Request $request): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'counter_code' => ['nullable', 'string', 'max:40'],
            'opening_cash' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->ensureOwnedRecord($organizationId, 'branches', (int) $validated['branch_id']);

        $alreadyOpen = CounterSession::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('branch_id', $validated['branch_id'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->exists();
        if ($alreadyOpen) {
            return back()->with('toast', [
                'type' => 'warning',
                'title' => __('app.already_open'),
                'text' => __('app.counter_session_already_open'),
            ]);
        }

        CounterSession::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'branch_id' => $validated['branch_id'],
            'user_id' => $request->user()->id,
            'counter_code' => $validated['counter_code'] ?? null,
            'status' => 'open',
            'opening_cash' => $validated['opening_cash'] ?? 0,
            'notes' => $validated['notes'] ?? null,
            'opened_at' => now(),
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'title' => __('app.saved'),
            'text' => __('app.counter_session_opened'),
        ]);
    }

    public function closeCounterSession(Request $request, CounterSession $counterSession): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        abort_if((int) $counterSession->organization_id !== $organizationId, 403);
        abort_if($counterSession->status !== 'open', 422, __('app.counter_session_not_open'));

        $validated = $request->validate([
            'closing_cash' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $counterSession->status = 'closed';
        $counterSession->closing_cash = $validated['closing_cash'] ?? null;
        $counterSession->closed_at = now();
        if (! empty($validated['notes'])) {
            $counterSession->notes = trim(($counterSession->notes ? $counterSession->notes."\n" : '').$validated['notes']);
        }
        $counterSession->save();

        return back()->with('toast', [
            'type' => 'success',
            'title' => __('app.updated'),
            'text' => __('app.counter_session_closed'),
        ]);
    }

    public function sell(Request $request): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'counter_session_id' => ['nullable', 'integer', 'exists:counter_sessions,id'],
            'invoice_type' => ['nullable', 'in:retail_cash,retail_credit,wholesale'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['nullable', 'array'],
            'payments.*.payment_method' => ['required_with:payments', 'string', 'max:30'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'gt:0'],
        ]);

        $this->ensureOwnedRecord($organizationId, 'branches', (int) $validated['branch_id']);
        if (! empty($validated['counter_session_id'])) {
            $this->ensureOwnedRecord($organizationId, 'counter_sessions', (int) $validated['counter_session_id']);
        }
        foreach ($validated['items'] as $item) {
            $this->ensureOwnedRecord($organizationId, 'products', (int) $item['product_id']);
            if (! empty($item['batch_id'])) {
                $this->ensureOwnedRecord($organizationId, 'batches', (int) $item['batch_id']);
            }
        }

        $this->tenantContext->setOrganizationId($organizationId);
        $this->tenantContext->setBranchId((int) $validated['branch_id']);

        $this->posSaleService->post([
            ...$validated,
            'idempotency_key' => 'web-pos-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4)),
        ], $request->user()?->id);

        return back()->with('toast', [
            'type' => 'success',
            'title' => __('app.saved'),
            'text' => __('app.sale_posted'),
        ]);
    }

    public function salesReturn(Request $request): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $validated = $request->validate([
            'sales_invoice_id' => ['required', 'integer', 'exists:sales_invoices,id'],
            'reason' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sales_invoice_item_id' => ['required', 'integer', 'exists:sales_invoice_items,id'],
            'items.*.return_qty' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->ensureOwnedRecord($organizationId, 'sales_invoices', (int) $validated['sales_invoice_id']);
        foreach ($validated['items'] as $item) {
            $this->ensureOwnedRecord($organizationId, 'sales_invoice_items', (int) $item['sales_invoice_item_id']);
            $invoiceItem = SalesInvoiceItem::withoutGlobalScopes()->find($item['sales_invoice_item_id']);
            abort_if((int) $invoiceItem->sales_invoice_id !== (int) $validated['sales_invoice_id'], 422, __('app.invoice_item_mismatch'));
        }

        $invoice = SalesInvoice::withoutGlobalScopes()->findOrFail($validated['sales_invoice_id']);
        $this->tenantContext->setOrganizationId($organizationId);
        $this->tenantContext->setBranchId((int) $invoice->branch_id);

        $this->salesReturnService->post($validated, $request->user()?->id);

        return back()->with('toast', [
            'type' => 'success',
            'title' => __('app.saved'),
            'text' => __('app.return_posted'),
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
