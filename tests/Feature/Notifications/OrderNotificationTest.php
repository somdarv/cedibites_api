<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderCompletedNotification;
use App\Notifications\OrderConfirmedNotification;
use App\Notifications\OrderOutForDeliveryNotification;
use App\Notifications\OrderPreparingNotification;
use App\Notifications\OrderReadyNotification;
use Illuminate\Support\Facades\Notification;

describe('Order Notifications', function () {
    beforeEach(function () {
        Notification::fake();
    });

    test('customer receives notification when order is created', function () {
        $user = User::factory()->create(['email' => 'customer@example.com']);
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $branch = Branch::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
        ]);

        Notification::assertSentTo(
            $user,
            OrderConfirmedNotification::class
        );
    });

    test('customer receives notification when order status changes to preparing', function () {
        $user = User::factory()->create(['email' => 'customer@example.com']);
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $branch = Branch::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'status' => 'received',
        ]);

        Notification::fake();

        $order->update(['status' => 'preparing']);

        Notification::assertSentTo(
            $user,
            OrderPreparingNotification::class
        );
    });

    test('customer receives notification when order is ready', function () {
        $user = User::factory()->create(['email' => 'customer@example.com']);
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $branch = Branch::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'status' => 'preparing',
        ]);

        Notification::fake();

        $order->update(['status' => 'ready']);

        Notification::assertSentTo(
            $user,
            OrderReadyNotification::class
        );
    });

    test('customer receives notification when order is out for delivery', function () {
        $user = User::factory()->create(['email' => 'customer@example.com']);
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $branch = Branch::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'status' => 'ready',
            'order_type' => 'delivery',
        ]);

        Notification::fake();

        $order->update(['status' => 'out_for_delivery']);

        Notification::assertSentTo(
            $user,
            OrderOutForDeliveryNotification::class
        );
    });

    test('customer receives notification when order is completed', function () {
        $user = User::factory()->create(['email' => 'customer@example.com']);
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $branch = Branch::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'status' => 'out_for_delivery',
        ]);

        Notification::fake();

        $order->update(['status' => 'completed']);

        Notification::assertSentTo(
            $user,
            OrderCompletedNotification::class
        );
    });

    test('customer receives notification when order is cancelled', function () {
        $user = User::factory()->create(['email' => 'customer@example.com']);
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $branch = Branch::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'status' => 'received',
        ]);

        Notification::fake();

        $order->update([
            'status' => 'cancelled',
            'cancelled_reason' => 'Customer request',
        ]);

        Notification::assertSentTo(
            $user,
            OrderCancelledNotification::class
        );
    });

    test('notification includes correct channels for user with email', function () {
        $user = User::factory()->create(['email' => 'customer@example.com']);
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $branch = Branch::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
        ]);

        $notification = new OrderConfirmedNotification($order);
        $channels = $notification->via($user);

        expect($channels)->toContain('database', 'mail', \App\Channels\SmsChannel::class);
    });

    test('notification skips email channel for user without email', function () {
        $user = User::factory()->create(['email' => null]);
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $branch = Branch::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
        ]);

        $notification = new OrderConfirmedNotification($order);
        $channels = $notification->via($user);

        expect($channels)->toContain('database', \App\Channels\SmsChannel::class)
            ->not->toContain('mail');
    });
});
