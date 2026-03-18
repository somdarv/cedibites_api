<?php

use App\Models\Payment;

test('factory creates valid payment records', function () {
    $payment = Payment::factory()->create();

    expect($payment)->toBeInstanceOf(Payment::class)
        ->and($payment->order_id)->toBeInt()
        ->and($payment->amount)->toBeNumeric();
});

test('mobileMoney state creates payment with mobile_money method', function () {
    $payment = Payment::factory()->mobileMoney()->create();

    expect($payment->payment_method)->toBe('mobile_money')
        ->and($payment->transaction_id)->not->toBeNull();
});

test('card state creates payment with card method', function () {
    $payment = Payment::factory()->card()->create();

    expect($payment->payment_method)->toBe('card')
        ->and($payment->transaction_id)->not->toBeNull();
});

test('wallet state creates payment with wallet method', function () {
    $payment = Payment::factory()->wallet()->create();

    expect($payment->payment_method)->toBe('wallet')
        ->and($payment->transaction_id)->not->toBeNull();
});

test('pending state creates payment with pending status', function () {
    $payment = Payment::factory()->pending()->create();

    expect($payment->payment_status)->toBe('pending')
        ->and($payment->paid_at)->toBeNull();
});

test('completed state creates payment with completed status and paid_at timestamp', function () {
    $payment = Payment::factory()->completed()->create();

    expect($payment->payment_status)->toBe('completed')
        ->and($payment->paid_at)->not->toBeNull();
});

test('factory states can be combined', function () {
    $payment = Payment::factory()
        ->mobileMoney()
        ->completed()
        ->create();

    expect($payment->payment_method)->toBe('mobile_money')
        ->and($payment->payment_status)->toBe('completed')
        ->and($payment->transaction_id)->not->toBeNull()
        ->and($payment->paid_at)->not->toBeNull();
});
