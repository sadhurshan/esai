<?php

use App\Http\Controllers\Api\SupplierSelfServiceController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\PlanController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SupplierSettingsController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::view('settings/company-profile', 'app')->name('company-profile.edit');

    Route::get('settings/supplier', SupplierSettingsController::class)
        ->name('settings.supplier.index');

    Route::prefix('api/me')->group(function (): void {
        Route::get('supplier-application/status', [SupplierSelfServiceController::class, 'status']);
        Route::put('supplier/visibility', [SupplierSelfServiceController::class, 'updateVisibility']);
    });

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/plan', [PlanController::class, 'show'])->name('plan.show');

    Route::view('settings/appearance', 'app')->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});
