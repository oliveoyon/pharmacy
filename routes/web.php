<?php

use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Admin\GoodsReceiptController as AdminGoodsReceiptController;
use App\Http\Controllers\Web\Admin\PosController as AdminPosController;
use App\Http\Controllers\Web\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Web\Admin\PurchaseOrderController as AdminPurchaseOrderController;
use App\Http\Controllers\Web\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Web\Admin\SupplierController as AdminSupplierController;
use App\Http\Controllers\Web\Admin\UnitController as AdminUnitController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('web.login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('web.logout');
Route::post('/locale/{locale}', LocaleController::class)->name('web.locale');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('can:units.manage')->group(function (): void {
        Route::get('/units', [AdminUnitController::class, 'index'])->name('units.index');
        Route::post('/units', [AdminUnitController::class, 'store'])->name('units.store');
        Route::put('/units/{unit}', [AdminUnitController::class, 'update'])->name('units.update');
        Route::delete('/units/{unit}', [AdminUnitController::class, 'destroy'])->name('units.destroy');
    });

    Route::middleware('can:suppliers.manage')->group(function (): void {
        Route::get('/suppliers', [AdminSupplierController::class, 'index'])->name('suppliers.index');
        Route::post('/suppliers', [AdminSupplierController::class, 'store'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [AdminSupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [AdminSupplierController::class, 'destroy'])->name('suppliers.destroy');
    });

    Route::middleware('can:products.manage')->group(function (): void {
        Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
        Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');
    });

    Route::middleware('can:purchase.manage')->group(function (): void {
        Route::get('/purchase-orders', [AdminPurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::post('/purchase-orders', [AdminPurchaseOrderController::class, 'store'])->name('purchase-orders.store');

        Route::get('/goods-receipts', [AdminGoodsReceiptController::class, 'index'])->name('goods-receipts.index');
        Route::post('/goods-receipts', [AdminGoodsReceiptController::class, 'store'])->name('goods-receipts.store');
    });

    Route::middleware('can:sales.pos')->group(function (): void {
        Route::get('/pos', [AdminPosController::class, 'index'])->name('pos.index');
        Route::post('/pos/sales', [AdminPosController::class, 'sell'])->name('pos.sales');
    });
    Route::middleware('can:counter.session.manage')->group(function (): void {
        Route::post('/pos/counter-sessions/open', [AdminPosController::class, 'openCounterSession'])->name('pos.counter-sessions.open');
        Route::post('/pos/counter-sessions/{counterSession}/close', [AdminPosController::class, 'closeCounterSession'])->name('pos.counter-sessions.close');
    });
    Route::middleware('can:sales.return')->group(function (): void {
        Route::post('/pos/returns', [AdminPosController::class, 'salesReturn'])->name('pos.returns');
    });

    Route::middleware('can:reports.view')->group(function (): void {
        Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export/{reportType}', [AdminReportController::class, 'export'])->name('reports.export');
    });
});
