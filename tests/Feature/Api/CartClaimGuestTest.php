<?php

use App\Models\Branch;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\User;

test('claims guest cart and assigns customer_id', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();
    $category = MenuCategory::factory()->create();
    $menuItem = MenuItem::factory()->create([
        'branch_id' => $branch->id,
        'category_id' => $category->id,
    ]);
    $menuItemSize = MenuItemSize::factory()->create([
        'menu_item_id' => $menuItem->id,
    ]);

    $guestSessionId = 'guest-'.fake()->unique()->numerify('##########').'-'.fake()->lexify('??????');

    $guestCart = Cart::factory()->guest()->create([
        'session_id' => $guestSessionId,
        'branch_id' => $branch->id,
    ]);

    CartItem::factory()->create([
        'cart_id' => $guestCart->id,
        'menu_item_id' => $menuItem->id,
        'menu_item_size_id' => $menuItemSize->id,
        'quantity' => 2,
        'unit_price' => 25.00,
        'subtotal' => 50.00,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/cart/claim-guest', [
            'guest_session_id' => $guestSessionId,
        ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'customer_id',
                'branch_id',
                'items',
            ],
        ]);

    expect($response->json('data.customer_id'))->toBe($customer->id);
    expect($response->json('data.session_id'))->toBeNull();

    $guestCart->refresh();
    expect($guestCart->customer_id)->toBe($customer->id);
    expect($guestCart->session_id)->toBeNull();
    expect($guestCart->items)->toHaveCount(1);
});

test('rejects unauthenticated claim request', function () {
    $response = $this->postJson('/api/v1/cart/claim-guest', [
        'guest_session_id' => 'guest-1234567890123456-abcdef',
    ]);

    $response->assertUnauthorized();
});

test('validates guest_session_id is required', function () {
    $user = User::factory()->create();
    Customer::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/cart/claim-guest', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['guest_session_id']);
});

test('returns customer cart when no guest carts exist', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $branch = Branch::factory()->create();

    $customerCart = Cart::factory()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/cart/claim-guest', [
            'guest_session_id' => 'guest-1234567890123456-abcdef',
        ]);

    $response->assertOk();
    expect($response->json('data.id'))->toBe($customerCart->id);
});
