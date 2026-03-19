<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

it('allows valid status transition from received to preparing', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['status' => 'received']);

    $this->actingAs($user)
        ->patchJson("/api/v1/orders/{$order->id}", ['status' => 'preparing'])
        ->assertSuccessful();

    expect($order->fresh()->status)->toBe('preparing');
});

it('rejects invalid status transition from received to completed', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['status' => 'received']);

    $this->actingAs($user)
        ->patchJson("/api/v1/orders/{$order->id}", ['status' => 'completed'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('rejects transition from a terminal status', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['status' => 'completed']);

    $this->actingAs($user)
        ->patchJson("/api/v1/orders/{$order->id}", ['status' => 'preparing'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('rejects transition from cancelled status', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['status' => 'cancelled']);

    $this->actingAs($user)
        ->patchJson("/api/v1/orders/{$order->id}", ['status' => 'received'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('allows all valid transitions in the happy path', function (string $from, string $to) {
    $user = User::factory()->create();
    $order = Order::factory()->create(['status' => $from]);

    $this->actingAs($user)
        ->patchJson("/api/v1/orders/{$order->id}", ['status' => $to])
        ->assertSuccessful();

    expect($order->fresh()->status)->toBe($to);
})->with([
    ['received', 'accepted'],
    ['received', 'preparing'],
    ['accepted', 'preparing'],
    ['preparing', 'ready'],
    ['ready', 'out_for_delivery'],
    ['ready', 'ready_for_pickup'],
    ['ready', 'completed'],
    ['out_for_delivery', 'delivered'],
    ['ready_for_pickup', 'completed'],
    ['delivered', 'completed'],
]);

it('allows cancellation from active statuses', function (string $from) {
    $user = User::factory()->create();
    $order = Order::factory()->create(['status' => $from]);

    $this->actingAs($user)
        ->patchJson("/api/v1/orders/{$order->id}", ['status' => 'cancelled'])
        ->assertSuccessful();
})->with(['received', 'accepted', 'preparing', 'ready', 'out_for_delivery', 'ready_for_pickup']);

it('allows update without changing status', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create(['status' => 'received', 'delivery_note' => null]);

    $this->actingAs($user)
        ->patchJson("/api/v1/orders/{$order->id}", ['delivery_note' => 'No onions'])
        ->assertSuccessful();

    expect($order->fresh()->delivery_note)->toBe('No onions');
    expect($order->fresh()->status)->toBe('received');
});
