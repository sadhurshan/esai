<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('home'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

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

    Route::get('/orders', fn () => Inertia::render('orders/index'))
        ->name('orders.index');
});

require __DIR__.'/settings.php';
