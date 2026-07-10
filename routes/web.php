<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryReceiptController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\Reports\MonthlySummaryController;
use App\Http\Controllers\Reports\StockCardController;
use App\Http\Controllers\ItemVariantController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SiteTeamController;
use App\Http\Controllers\TransferSlipController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WithdrawalSlipController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

// Guest
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

// Authenticated
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Sites CRUD + engineer assignment (administrator)
    Route::get('sites', [SiteController::class, 'index'])->name('sites.index');
    Route::post('sites', [SiteController::class, 'store'])->name('sites.store');
    Route::put('sites/{site}', [SiteController::class, 'update'])->name('sites.update');
    Route::delete('sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
    Route::put('sites/{site}/engineers', [SiteController::class, 'syncEngineers'])->name('sites.engineers');

    // Per-site ICS assignment (engineer, own sites only)
    Route::get('sites/{site}/team', [SiteTeamController::class, 'edit'])->name('sites.team');
    Route::put('sites/{site}/team', [SiteTeamController::class, 'update'])->name('sites.team.update');

    // Items master
    Route::get('items', [ItemController::class, 'index'])->name('items.index');
    Route::get('items/{item}', [ItemController::class, 'show'])->name('items.show');
    Route::post('items', [ItemController::class, 'store'])->name('items.store');
    Route::put('items/{item}', [ItemController::class, 'update'])->name('items.update');
    Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');

    // Item variants (stockable units)
    Route::post('items/{item}/variants', [ItemVariantController::class, 'store'])->name('variants.store');
    Route::put('items/{item}/variants/{variant}', [ItemVariantController::class, 'update'])->name('variants.update');
    Route::put('items/{item}/variants/{variant}/default', [ItemVariantController::class, 'setDefault'])->name('variants.default');
    Route::delete('items/{item}/variants/{variant}', [ItemVariantController::class, 'destroy'])->name('variants.destroy');

    // Scoped stock views
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');

    // Receiving (delivery receipts)
    Route::get('receiving', [DeliveryReceiptController::class, 'index'])->name('receiving.index');
    Route::get('receiving/create', [DeliveryReceiptController::class, 'create'])->name('receiving.create');
    Route::post('receiving', [DeliveryReceiptController::class, 'store'])->name('receiving.store');
    Route::get('receiving/{receiving}', [DeliveryReceiptController::class, 'show'])->name('receiving.show');
    Route::post('receiving/{receiving}/post', [DeliveryReceiptController::class, 'post'])->name('receiving.post');
    Route::post('receiving/{receiving}/cancel', [DeliveryReceiptController::class, 'cancel'])->name('receiving.cancel');

    // Withdrawal slips (F-INV-001) — NO RELEASE WITHOUT APPROVAL
    Route::get('withdrawals', [WithdrawalSlipController::class, 'index'])->name('withdrawals.index');
    Route::get('withdrawals/create', [WithdrawalSlipController::class, 'create'])->name('withdrawals.create');
    Route::post('withdrawals', [WithdrawalSlipController::class, 'store'])->name('withdrawals.store');
    Route::get('withdrawals/{withdrawal}', [WithdrawalSlipController::class, 'show'])->name('withdrawals.show');
    Route::post('withdrawals/{withdrawal}/submit', [WithdrawalSlipController::class, 'submit'])->name('withdrawals.submit');
    Route::post('withdrawals/{withdrawal}/approve', [WithdrawalSlipController::class, 'approve'])->name('withdrawals.approve');
    Route::post('withdrawals/{withdrawal}/reject', [WithdrawalSlipController::class, 'reject'])->name('withdrawals.reject');
    Route::post('withdrawals/{withdrawal}/release', [WithdrawalSlipController::class, 'release'])->name('withdrawals.release');
    Route::post('withdrawals/{withdrawal}/receive', [WithdrawalSlipController::class, 'receive'])->name('withdrawals.receive');
    Route::post('withdrawals/{withdrawal}/cancel', [WithdrawalSlipController::class, 'cancel'])->name('withdrawals.cancel');

    // Transfer slips (F-INV-004)
    Route::get('transfers', [TransferSlipController::class, 'index'])->name('transfers.index');
    Route::get('transfers/create', [TransferSlipController::class, 'create'])->name('transfers.create');
    Route::post('transfers', [TransferSlipController::class, 'store'])->name('transfers.store');
    Route::get('transfers/{transfer}', [TransferSlipController::class, 'show'])->name('transfers.show');
    Route::post('transfers/{transfer}/dispatch', [TransferSlipController::class, 'dispatchTransfer'])->name('transfers.dispatch');
    Route::post('transfers/{transfer}/receive', [TransferSlipController::class, 'receive'])->name('transfers.receive');
    Route::post('transfers/{transfer}/cancel', [TransferSlipController::class, 'cancel'])->name('transfers.cancel');

    // Reports
    Route::get('reports/stock-card', [StockCardController::class, 'index'])->name('reports.stock-card');
    Route::get('reports/monthly-summary', [MonthlySummaryController::class, 'index'])->name('reports.monthly-summary');

    // Users (administrator)
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});
