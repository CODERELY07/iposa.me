<?php

use App\Http\Controllers\Admin\AssetController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\ItemController;
use App\Http\Controllers\Admin\ItemImportController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\VoidRequestController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Pos\OrderController;
use App\Http\Controllers\Pos\RegisterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceWorkerController;
use App\Http\Controllers\Staff\MyOrdersController;
use App\Http\Controllers\SuperAdmin\PlanController;
use App\Http\Controllers\SuperAdmin\PlatformDashboardController;
use App\Http\Controllers\SuperAdmin\SubscriptionPaymentController;
use App\Http\Controllers\SuperAdmin\VerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// PWA service worker (versioned with the Vite build, see ServiceWorkerController).
Route::get('/sw.js', ServiceWorkerController::class)->name('pwa.service-worker');

Route::get('/dashboard', fn () => redirect()->route(auth()->user()->homeRoute()))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Counter screens: cashiers live here, owners can ring up too.
Route::middleware(['auth', 'verified', 'role:staff|admin', 'business'])->group(function () {
    Route::get('/pos', [RegisterController::class, 'index'])->name('pos');
    Route::post('/pos/orders', [RegisterController::class, 'store'])->name('pos.orders.store');
    Route::get('/pos/orders/{order}/receipt', [OrderController::class, 'receipt'])->name('pos.orders.receipt');
    Route::post('/pos/orders/{order}/void', [OrderController::class, 'void'])->name('pos.orders.void');

    Route::middleware('can:run-audit')->group(function () {
        Route::get('/audit', [AuditController::class, 'index'])->name('audit');
        Route::post('/audit', [AuditController::class, 'store'])->name('audit.store');
    });

    Route::post('/expenses', [ExpenseController::class, 'store'])
        ->middleware(['can:log-expenses', 'plan:expenses'])
        ->name('expenses.store');
});

// Cashier screens
Route::middleware(['auth', 'verified', 'role:staff', 'business'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/orders', MyOrdersController::class)->name('orders');
});

// Owner screens
Route::middleware(['auth', 'verified', 'role:admin', 'business'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/inventory', [ItemController::class, 'index'])->name('inventory');
    Route::get('/inventory/items/new', [ItemController::class, 'create'])->name('inventory.create');
    Route::post('/inventory/items', [ItemController::class, 'store'])->name('inventory.store');
    Route::get('/inventory/items/{item}/edit', [ItemController::class, 'edit'])->name('inventory.edit');
    Route::put('/inventory/items/{item}', [ItemController::class, 'update'])->name('inventory.update');
    Route::patch('/inventory/items/{item}/archive', [ItemController::class, 'archive'])->name('inventory.archive');
    Route::patch('/inventory/items/{item}/restore', [ItemController::class, 'restore'])->name('inventory.restore');
    Route::delete('/inventory/items/{item}', [ItemController::class, 'destroy'])->name('inventory.destroy');
    Route::post('/inventory/import', ItemImportController::class)->name('inventory.import');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');

    Route::post('/orders/{order}/void/approve', [VoidRequestController::class, 'approve'])->name('orders.void.approve');
    Route::post('/orders/{order}/void/reject', [VoidRequestController::class, 'reject'])->name('orders.void.reject');

    Route::middleware('plan:expenses')->group(function () {
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
        Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
        Route::post('/assets/{asset}/pay', [AssetController::class, 'pay'])->name('assets.pay');
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
    });

    Route::get('/reports', ReportController::class)->middleware('plan:reports')->name('reports');
    Route::get('/exports/{dataset}', ExportController::class)
        ->whereIn('dataset', ExportController::DATASETS)
        ->name('exports.download');

    Route::get('/team', [TeamController::class, 'index'])->name('team');
    Route::post('/team', [TeamController::class, 'store'])->name('team.store');
    Route::post('/team/{user}/resend', [TeamController::class, 'resend'])->name('team.resend');
    Route::patch('/team/{user}/password', [TeamController::class, 'setPassword'])->name('team.password');
    Route::delete('/team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');
    Route::patch('/team/permissions', [TeamController::class, 'updatePermissions'])->name('team.permissions');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
    Route::patch('/settings/business', [SettingsController::class, 'updateBusiness'])->name('settings.business');
    Route::patch('/settings/register', [SettingsController::class, 'updateRegister'])->name('settings.register');
    Route::patch('/billing/plan', [BillingController::class, 'changePlan'])->name('billing.plan');
    Route::post('/billing/payments', [BillingController::class, 'submitPayment'])->name('billing.payments.store');
});

// Platform console (SaaS operator)
Route::middleware(['auth', 'verified', 'role:super_admin'])->prefix('super-admin')->name('super_admin.')->group(function () {
    Route::get('/', PlatformDashboardController::class)->name('dashboard');
    Route::resource('businesses', BusinessController::class)->only(['index', 'show', 'edit', 'update']);
    Route::post('/businesses/{business}/extend-trial', [BusinessController::class, 'extendTrial'])->name('businesses.extend-trial');
    Route::post('/businesses/{business}/suspend', [BusinessController::class, 'suspend'])->name('businesses.suspend');
    Route::post('/businesses/{business}/unsuspend', [BusinessController::class, 'unsuspend'])->name('businesses.unsuspend');
    Route::get('/plans', [PlanController::class, 'index'])->name('plans');
    Route::get('/plans/new', [PlanController::class, 'create'])->name('plans.create');
    Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
    Route::get('/plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
    Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::patch('/plans/{plan}/archive', [PlanController::class, 'archive'])->name('plans.archive');
    Route::patch('/plans/{plan}/restore', [PlanController::class, 'restore'])->name('plans.restore');
    Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');
    Route::get('/verifications', [VerificationController::class, 'index'])->name('verifications');
    Route::post('/users/{user}/verify', [VerificationController::class, 'verify'])->name('users.verify');
    Route::post('/payments/{payment}/confirm', [SubscriptionPaymentController::class, 'confirm'])->name('payments.confirm');
    Route::post('/payments/{payment}/reject', [SubscriptionPaymentController::class, 'reject'])->name('payments.reject');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
