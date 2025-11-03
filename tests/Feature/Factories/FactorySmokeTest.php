<?php

use App\Models\Order;
use App\Models\RFQ;
use App\Models\RFQQuote;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a supplier via factory', function () {
    $supplier = Supplier::factory()->create();

    expect($supplier->exists)->toBeTrue()
        ->and($supplier->capabilities)->toBeArray()
        ->and($supplier->materials)->toBeArray();
});

it('creates an rfq via factory', function () {
    $rfq = RFQ::factory()->create();

    expect($rfq->exists)->toBeTrue()
        ->and($rfq->number)->toMatch('/^\d{5}$/');
});

it('creates an rfq quote via factory', function () {
    $quote = RFQQuote::factory()
        ->for(RFQ::factory())
        ->for(Supplier::factory())
        ->create();

    expect($quote->exists)->toBeTrue()
        ->and($quote->submitted_at)->not->toBeNull();
});

it('creates an order via factory', function () {
    $order = Order::factory()->create();

    expect($order->exists)->toBeTrue()
        ->and($order->number)->toMatch('/^PO-\d{5}$/');
});
