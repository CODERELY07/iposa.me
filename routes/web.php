<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {

    $role = auth()->user()->role;
    // dd($role);
    return match($role){
        'admin' => redirect()->route('admin.dashboard'),
        'super_admin' => redirect()->route('super_admin.dashbord'),
        'staff' => redirect()->route('staff.dashbord'),
        default => redirect('/'),
    };
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('super_admin', function(){
    return view('super_admin.dashboard');
})->middleware('role:super_admin')->name('super_admin.dashboard');

Route::get('admin', function(){
    return view('admin.dashboard');
})->middleware('role:admin')->name('admin.dashboard');

Route::get('staff', function(){
    return view('staff.dashboard');
})->middleware('role:staff')->name('staff.dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
