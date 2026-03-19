<?php

use App\Models\Branch;
use App\Models\Order;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('returns active orders for order manager without authentication', function () {
    $branch = Branch::factory()->create();

    Order::factory()->create(['branch_id' => $branch->id, 'status' => 'received']);
    Order::factory()->create(['branch_id' => $branch->id, 'status' => 'preparing']);
    Order::factory()->create(['branch_id' => $branch->id, 'status' => 'ready']);
    Order::factory()->create(['branch_id' => $branch->id, 'status' => 'completed']);

    $response = $this->getJson('/api/v1/order-manager/orders');

    $response->assertSuccessful();
    $response->assertJsonCount(3, 'data');
});

it('excludes completed and cancelled orders', function () {
    $branch = Branch::factory()->create();

    Order::factory()->create(['branch_id' => $branch->id, 'status' => 'completed']);
    Order::factory()->create(['branch_id' => $branch->id, 'status' => 'cancelled']);

    $response = $this->getJson('/api/v1/order-manager/orders');

    $response->assertSuccessful();
    $response->assertJsonCount(0, 'data');
});

it('filters orders by branch_id', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    Order::factory()->create(['branch_id' => $branchA->id, 'status' => 'received']);
    Order::factory()->create(['branch_id' => $branchB->id, 'status' => 'received']);

    $response = $this->getJson("/api/v1/order-manager/orders?branch_id={$branchA->id}");

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'data');
});

it('is accessible without a bearer token', function () {
    $this->getJson('/api/v1/order-manager/orders')->assertSuccessful();
});
