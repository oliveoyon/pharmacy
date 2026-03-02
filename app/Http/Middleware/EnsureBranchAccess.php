<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->is_platform_user) {
            return $next($request);
        }

        $branchIds = collect();
        $contextBranchId = app(TenantContext::class)->branchId();
        if ($contextBranchId !== null) {
            $branchIds->push((int) $contextBranchId);
        }

        if ($request->filled('branch_id')) {
            $branchIds->push((int) $request->integer('branch_id'));
        }

        $branchRoute = $request->route('branch');
        if ($branchRoute !== null) {
            $branchIds->push((int) (is_object($branchRoute) ? $branchRoute->id : $branchRoute));
        }

        $purchaseOrderRoute = $request->route('purchaseOrder');
        if ($purchaseOrderRoute !== null) {
            $purchaseOrder = $purchaseOrderRoute instanceof PurchaseOrder
                ? $purchaseOrderRoute
                : PurchaseOrder::query()->find($purchaseOrderRoute);
            if ($purchaseOrder) {
                $branchIds->push((int) $purchaseOrder->branch_id);
            }
        }

        $salesInvoiceRoute = $request->route('salesInvoice');
        if ($salesInvoiceRoute !== null) {
            $salesInvoice = $salesInvoiceRoute instanceof SalesInvoice
                ? $salesInvoiceRoute
                : SalesInvoice::query()->find($salesInvoiceRoute);
            if ($salesInvoice) {
                $branchIds->push((int) $salesInvoice->branch_id);
            }
        }

        $counterSessionRoute = $request->route('counterSession');
        if ($counterSessionRoute !== null) {
            $branchIds->push((int) (is_object($counterSessionRoute) ? $counterSessionRoute->branch_id : 0));
        }

        foreach ($branchIds->filter(fn (int $id) => $id > 0)->unique()->all() as $branchId) {
            if (! Branch::withoutGlobalScopes()->whereKey($branchId)->exists()) {
                abort(404, 'Branch not found.');
            }

            if (! $user->hasBranchAccess($branchId)) {
                abort(403, 'No access to this branch.');
            }
        }

        return $next($request);
    }
}
