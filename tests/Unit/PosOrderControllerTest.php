<?php

use App\Http\Controllers\Api\PosOrderController;
use App\Http\Requests\StorePosOrderRequest;
use Illuminate\Http\JsonResponse;

test('controller exists and has store method', function () {
    $controller = new PosOrderController;

    expect($controller)->toBeInstanceOf(PosOrderController::class);
    expect(method_exists($controller, 'store'))->toBeTrue();
});

test('store method accepts StorePosOrderRequest', function () {
    $reflection = new ReflectionClass(PosOrderController::class);
    $method = $reflection->getMethod('store');
    $parameters = $method->getParameters();

    expect($parameters)->toHaveCount(1);
    expect($parameters[0]->getName())->toBe('request');
    expect($parameters[0]->getType()->getName())->toBe(StorePosOrderRequest::class);
});

test('store method returns JsonResponse', function () {
    $reflection = new ReflectionClass(PosOrderController::class);
    $method = $reflection->getMethod('store');
    $returnType = $method->getReturnType();

    expect($returnType)->not->toBeNull();
    expect($returnType->getName())->toBe(JsonResponse::class);
});

test('controller has required constants', function () {
    $reflection = new ReflectionClass(PosOrderController::class);

    expect($reflection->hasConstant('TAX_RATE'))->toBeTrue();
    expect($reflection->hasConstant('PRICE_TOLERANCE'))->toBeTrue();

    expect($reflection->getConstant('TAX_RATE'))->toBe(0.025);
    expect($reflection->getConstant('PRICE_TOLERANCE'))->toBe(0.01);
});
