<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $organizationId = $user?->organization_id;

        $stats = [
            'branches' => $organizationId ? Branch::withoutGlobalScopes()->where('organization_id', $organizationId)->count() : 0,
            'suppliers' => $organizationId ? Supplier::withoutGlobalScopes()->where('organization_id', $organizationId)->count() : 0,
            'products' => $organizationId ? Product::withoutGlobalScopes()->where('organization_id', $organizationId)->count() : 0,
            'sales_today' => $organizationId
                ? (float) SalesInvoice::withoutGlobalScopes()
                    ->where('organization_id', $organizationId)
                    ->whereDate('sold_at', now()->toDateString())
                    ->sum('grand_total')
                : 0.0,
        ];

        return view('web.admin.dashboard', compact('stats'));
    }
}

