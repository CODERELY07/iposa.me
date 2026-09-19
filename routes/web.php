<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| UI prototype routes
|--------------------------------------------------------------------------
| Every screen below renders a view with static demo data declared at the
| top of the view. Swap the closures for controllers when wiring real data.
*/

Route::view('/', 'welcome')->name('home');

Route::get('/dashboard', function () {
    return match (auth()->user()->role) {
        'super_admin' => redirect()->route('super_admin.dashboard'),
        'admin'       => redirect()->route('admin.dashboard'),
        'staff'       => redirect()->route('pos'),
        default       => redirect()->route('home'),
    };
})->middleware(['auth', 'verified'])->name('dashboard');

// Shared counter screens: cashiers live here, owners can ring up too.
Route::middleware(['auth', 'verified', 'role:staff|admin'])->group(function () {
    Route::view('/pos', 'pos.index')->name('pos');
    Route::view('/audit', 'audit.index')->name('audit');
});

// Staff screens
Route::middleware(['auth', 'verified', 'role:staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::view('/orders', 'staff.orders')->name('orders');
});

// Admin screens
Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.dashboard')->name('dashboard');
    Route::view('/inventory', 'admin.inventory.index')->name('inventory');
    Route::view('/inventory/items/new', 'admin.inventory.item')->name('inventory.create');
    Route::view('/inventory/items/{item}/edit', 'admin.inventory.item')->name('inventory.edit');
    Route::view('/expenses', 'admin.expenses')->name('expenses');
    Route::view('/reports', 'admin.reports')->name('reports');
    Route::view('/team', 'admin.team')->name('team');
    Route::view('/settings', 'admin.settings')->name('settings');
});

// Super Admin screens
Route::middleware(['auth', 'verified', 'role:super_admin'])->prefix('super-admin')->name('super_admin.')->group(function () {
    Route::view('/', 'super_admin.dashboard')->name('dashboard');
    Route::view('/businesses', 'super_admin.tenants.index')->name('tenants');
    Route::view('/businesses/{tenant}', 'super_admin.tenants.show')->name('tenants.show');
    Route::view('/plans', 'super_admin.plans')->name('plans');
});

// Profile Management screens
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
