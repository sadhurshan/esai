<?php

use App\Http\Controllers\Api\Auth\AuthSessionController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\SelfRegistrationController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app')->name('home');

Route::prefix('api/auth')->group(function (): void {
    Route::post('login', [AuthSessionController::class, 'store'])->middleware('guest');
    Route::post('register', [SelfRegistrationController::class, 'register'])->middleware('guest');

    Route::middleware('auth')->group(function (): void {
        Route::get('me', [AuthSessionController::class, 'show']);
        Route::post('logout', [AuthSessionController::class, 'destroy']);
    });

    Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('guest');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('guest');
});

Route::middleware(['auth'])->group(function () {
    Route::view('/company-registration', 'app')->name('company.registration');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/user/password', [PasswordController::class, 'edit'])->name('user-password.edit');
    Route::put('/user/password', [PasswordController::class, 'update'])->name('user-password.update');

    Route::get('/two-factor', [TwoFactorAuthenticationController::class, 'show'])->name('two-factor.show');
});

Route::middleware(['auth', 'verified', 'ensure.company.registered'])->group(function () {
    Route::view('/app', 'app')->name('dashboard');

    Route::view('/app/suppliers', 'app')->name('suppliers.index');

    Route::view('/app/rfqs', 'app')->name('rfq.index');

    Route::view('/app/rfqs/new', 'app')->name('rfq.new');

    Route::view('/app/rfqs/{id}', 'app')
        ->whereNumber('id')
        ->name('rfq.show');

    Route::view('/app/rfqs/{id}/open', 'app')
        ->whereNumber('id')
        ->name('rfq.open');

    Route::view('/app/rfqs/{id}/compare', 'app')
        ->whereNumber('id')
        ->name('rfq.compare');

    Route::view('/app/orders', 'app')->name('orders.index');

    Route::view('/app/purchase-orders', 'app')->name('purchase-orders.index');

    Route::view('/app/purchase-orders/supplier', 'app')->name('purchase-orders.supplier.index');

    Route::view('/app/purchase-orders/{id}/supplier', 'app')
        ->whereNumber('id')
        ->name('purchase-orders.supplier.show');

    Route::view('/app/purchase-orders/{id}', 'app')
        ->whereNumber('id')
        ->name('purchase-orders.show');

    Route::view('/app/supplier/company-profile', 'app')->name('supplier.company-profile');

    Route::view('/app/inventory', 'app')->name('inventory.index');
    Route::view('/app/inventory/items', 'app')->name('inventory.items.index');
    Route::view('/app/inventory/items/new', 'app')->name('inventory.items.create');
    Route::view('/app/inventory/items/{itemId}', 'app')
        ->where('itemId', '[A-Za-z0-9_-]+')
        ->name('inventory.items.show');

    Route::view('/app/admin/companies', 'app')->name('admin.companies.index');

    Route::view('/app/{any}', 'app')
        ->where('any', '.*');
});

require __DIR__.'/settings.php';
