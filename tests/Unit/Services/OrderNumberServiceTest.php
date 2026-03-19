<?php

use App\Models\Order;
use App\Services\OrderNumberService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('starts at CB000001 when no orders exist', function () {
    $service = new OrderNumberService;

    expect($service->generate())->toBe('CB000001');
});

it('increments from an existing CB order number', function () {
    $service = new OrderNumberService;

    Order::factory()->create(['order_number' => 'CB000001']);

    expect($service->generate())->toBe('CB000002');
});

it('increments correctly at higher values', function () {
    $service = new OrderNumberService;

    Order::factory()->create(['order_number' => 'CB000099']);

    expect($service->generate())->toBe('CB000100');
});

it('pads to 6 digits correctly', function () {
    $service = new OrderNumberService;

    Order::factory()->create(['order_number' => 'CB000999']);

    expect($service->generate())->toBe('CB001000');
});

it('generates order numbers matching CB + 6 digit format', function () {
    $service = new OrderNumberService;

    expect($service->generate())->toMatch('/^CB\d{6}$/');
});
