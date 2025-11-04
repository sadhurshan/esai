<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('home'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('/company-registration', function () {
        return Inertia::render('company/registration');
    })->name('company.registration');

    Route::get('/suppliers', fn () => Inertia::render('suppliers/index'))
        ->name('suppliers.index');

    Route::get('/rfq', fn () => Inertia::render('rfq/index'))
        ->name('rfq.index');

    Route::get('/rfq/new', fn () => Inertia::render('rfq/new'))
        ->name('rfq.new');

    Route::get('/rfq/{id}', fn () => Inertia::render('rfq/show'))
        ->whereNumber('id')
        ->name('rfq.show');

    Route::get('/rfq/{id}/open', fn () => Inertia::render('rfq/open'))
        ->whereNumber('id')
        ->name('rfq.open');

    Route::get('/rfq/{id}/compare', fn () => Inertia::render('rfq/compare'))
        ->whereNumber('id')
        ->name('rfq.compare');

    Route::get('/orders', fn () => Inertia::render('orders/index'))
        ->name('orders.index');

    Route::inertia('purchase-orders', 'purchase-orders/index')
        ->name('purchase-orders.index');

    Route::inertia('purchase-orders/supplier', 'purchase-orders/supplier/index')
        ->name('purchase-orders.supplier.index');

    Route::get('/purchase-orders/{id}/supplier', fn ($id) => Inertia::render('purchase-orders/supplier/show', ['id' => $id]))
        ->whereNumber('id')
        ->name('purchase-orders.supplier.show');

    Route::get('/purchase-orders/{id}', fn ($id) => Inertia::render('purchase-orders/show', ['id' => $id]))
        ->whereNumber('id')
        ->name('purchase-orders.show');

    Route::inertia('supplier/company-profile', 'supplier/company-profile')
        ->name('supplier.company-profile');

    Route::inertia('admin/companies', 'admin/companies/index')
        ->name('admin.companies.index');
});

require __DIR__.'/settings.php';
