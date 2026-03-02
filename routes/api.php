<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\GoodsReceiptController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\PosController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReportScheduleController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\CounterSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login']);
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
        });
    });

    Route::prefix('platform/onboarding')
        ->middleware(['auth:sanctum', 'platform.user', 'can:platform.tenants.manage'])
        ->group(function (): void {
        Route::post('organizations', [OnboardingController::class, 'storeOrganization']);
        Route::post('organizations/{organization}/branches', [OnboardingController::class, 'storeBranch']);
    });

    Route::prefix('tenant')->middleware(['auth:sanctum', 'tenant.branch'])->group(function (): void {
        Route::get('units', [UnitController::class, 'index'])->middleware('can:units.manage');
        Route::post('units', [UnitController::class, 'store'])->middleware('can:units.manage');

        Route::get('suppliers', [SupplierController::class, 'index'])->middleware('can:suppliers.manage');
        Route::post('suppliers', [SupplierController::class, 'store'])->middleware('can:suppliers.manage');

        Route::get('products', [ProductController::class, 'index'])->middleware('can:products.manage');
        Route::post('products', [ProductController::class, 'store'])->middleware('can:products.manage');

        Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->middleware('can:purchase.manage');
        Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('can:purchase.manage');
        Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->middleware('can:purchase.manage');

        Route::get('goods-receipts', [GoodsReceiptController::class, 'index'])->middleware('can:purchase.manage');
        Route::post('goods-receipts', [GoodsReceiptController::class, 'store'])->middleware('can:purchase.manage');

        Route::post('counter-sessions/open', [CounterSessionController::class, 'open'])->middleware('can:counter.session.manage');
        Route::post('counter-sessions/{counterSession}/close', [CounterSessionController::class, 'close'])->middleware('can:counter.session.manage');

        Route::get('pos/invoices', [PosController::class, 'invoices'])->middleware('can:sales.pos');
        Route::post('pos/sales', [PosController::class, 'sell'])->middleware('can:sales.pos');
        Route::post('pos/returns', [PosController::class, 'salesReturn'])->middleware('can:sales.return');

        Route::get('reports/sales-summary', [ReportController::class, 'salesSummary'])->middleware('can:reports.view');
        Route::get('reports/stock-valuation', [ReportController::class, 'stockValuation'])->middleware('can:reports.view');
        Route::get('reports/expiry-alerts', [ReportController::class, 'expiryAlerts'])->middleware('can:reports.view');
        Route::get('reports/export/{reportType}', [ReportController::class, 'exportCsv'])->middleware('can:reports.view');

        Route::get('report-schedules', [ReportScheduleController::class, 'index'])->middleware('can:reports.view');
        Route::post('report-schedules', [ReportScheduleController::class, 'store'])->middleware('can:reports.view');
        Route::patch('report-schedules/{reportSchedule}', [ReportScheduleController::class, 'update'])->middleware('can:reports.view');
        Route::post('report-schedules/{reportSchedule}/run-now', [ReportScheduleController::class, 'runNow'])->middleware('can:reports.view');

        Route::post('inventory/adjustments', [InventoryController::class, 'adjust'])->middleware('can:inventory.adjust');
        Route::get('inventory/fefo-suggestion', [InventoryController::class, 'fefoSuggestion'])->middleware('can:inventory.view');
    });
});
