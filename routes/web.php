<?php

use App\Http\Controllers\AdminCashManagementController;
use App\Http\Controllers\CashierAuditExportController;
use App\Http\Controllers\DailyReportExportController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ReorderListExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('pos.index');
    }

    return view('welcome');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/admin/daily-report/export/csv', [DailyReportExportController::class, 'csv'])->name('admin.daily-report.export.csv');
    Route::get('/admin/daily-report/export/pdf', [DailyReportExportController::class, 'pdf'])->name('admin.daily-report.export.pdf');
    Route::get('/admin/cashier-audit/export/csv', [CashierAuditExportController::class, 'csv'])->name('admin.cashier-audit.export.csv');
    Route::get('/admin/cashier-audit/export/pdf', [CashierAuditExportController::class, 'pdf'])->name('admin.cashier-audit.export.pdf');
    Route::post('/admin/cash-management', [AdminCashManagementController::class, 'store'])->name('admin.cash-management.store');
    Route::get('/admin/reorder-list/export/csv', [ReorderListExportController::class, 'csv'])->name('admin.reorder-list.export.csv');

    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/shift/open', [PosController::class, 'openShift'])->name('pos.shift.open');
    Route::post('/pos/shift/close', [PosController::class, 'closeShift'])->name('pos.shift.close');
    Route::get('/pos/shifts/{cashierShift}/report', [PosController::class, 'shiftReport'])->name('pos.shifts.report');
    Route::post('/pos/drafts', [PosController::class, 'storeDraft'])->name('pos.drafts.store');
    Route::delete('/pos/drafts/{heldCart}', [PosController::class, 'destroyDraft'])->name('pos.drafts.destroy');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    Route::post('/pos/cash-movements', [PosController::class, 'storeCashMovement'])->name('pos.cash-movements.store');
    Route::get('/transactions/{saleTransaction}/receipt', [PosController::class, 'receipt'])->name('transactions.receipt');
    Route::post('/transactions/{saleTransaction}/void', [PosController::class, 'void'])->name('transactions.void');
    Route::post('/transactions/{saleTransaction}/refund', [PosController::class, 'refund'])->name('transactions.refund');
    Route::post('/transactions/{saleTransaction}/partial-refund', [PosController::class, 'partialRefund'])->name('transactions.partial-refund');
});
