<?php

use App\Services\HubtelSmsService;
use Mockery;

test('validateConfiguration throws exception when clientId is empty', function () {
    config(['services.hubtel.client_id' => '']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('validateConfiguration');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($service))
        ->toThrow(RuntimeException::class, 'Hubtel SMS is not properly configured');
});

test('validateConfiguration throws exception when clientSecret is empty', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => '']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('validateConfiguration');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($service))
        ->toThrow(RuntimeException::class, 'Hubtel SMS is not properly configured');
});

test('validateConfiguration throws exception when both clientId and clientSecret are empty', function () {
    config(['services.hubtel.client_id' => '']);
    config(['services.hubtel.client_secret' => '']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('validateConfiguration');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($service))
        ->toThrow(RuntimeException::class, 'Hubtel SMS is not properly configured');
});

test('validateConfiguration throws exception when clientId is null', function () {
    config(['services.hubtel.client_id' => null]);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('validateConfiguration');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($service))
        ->toThrow(RuntimeException::class, 'Hubtel SMS is not properly configured');
});

test('validateConfiguration throws exception when clientSecret is null', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => null]);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('validateConfiguration');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($service))
        ->toThrow(RuntimeException::class, 'Hubtel SMS is not properly configured');
});

test('validateConfiguration passes when both clientId and clientSecret are valid', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('validateConfiguration');
    $method->setAccessible(true);

    // Should not throw exception
    $method->invoke($service);

    expect(true)->toBeTrue();
});

test('validatePhoneNumber accepts valid Ghana phone number', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('validatePhoneNumber');
    $method->setAccessible(true);

    // Should not throw exception for valid phone
    $method->invoke($service, '233241234567');

    expect(true)->toBeTrue();
});

test('validatePhoneNumber throws exception for phone number too short', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('validatePhoneNumber');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($service, '23324123456'))
        ->toThrow(InvalidArgumentException::class, 'Invalid phone number format');
});

test('validatePhoneNumber throws exception for phone number too long', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('validatePhoneNumber');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($service, '2332412345678'))
        ->toThrow(InvalidArgumentException::class, 'Invalid phone number format');
});

test('validatePhoneNumber throws exception for phone number with wrong prefix', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('validatePhoneNumber');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($service, '234241234567'))
        ->toThrow(InvalidArgumentException::class, 'Invalid phone number format');
});

test('validatePhoneNumber throws exception for phone number with non-numeric characters', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('validatePhoneNumber');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($service, '23324123456a'))
        ->toThrow(InvalidArgumentException::class, 'Invalid phone number format');
});

test('validatePhoneNumber throws exception for phone number with spaces', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('validatePhoneNumber');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($service, '233 24123456'))
        ->toThrow(InvalidArgumentException::class, 'Invalid phone number format');
});

test('validatePhoneNumber throws exception for phone number with special characters', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('validatePhoneNumber');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($service, '233-24123456'))
        ->toThrow(InvalidArgumentException::class, 'Invalid phone number format');
});

test('validatePhoneNumber throws exception for empty phone number', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('validatePhoneNumber');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($service, ''))
        ->toThrow(InvalidArgumentException::class, 'Invalid phone number format');
});

test('sanitizeForLogging masks phone numbers correctly', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('sanitizeForLogging');
    $method->setAccessible(true);

    $data = [
        'phone' => '233241234567',
        'message' => 'Test message',
    ];

    $result = $method->invoke($service, $data);

    expect($result['phone'])->toBe('233****67');
    expect($result['message'])->toBe('Test message');
});

test('sanitizeForLogging removes clientSecret from data', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('sanitizeForLogging');
    $method->setAccessible(true);

    $data = [
        'clientSecret' => 'test_secret',
        'message' => 'Test message',
    ];

    $result = $method->invoke($service, $data);

    expect($result)->not->toHaveKey('clientSecret');
    expect($result['message'])->toBe('Test message');
});

test('sanitizeForLogging handles nested arrays', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('sanitizeForLogging');
    $method->setAccessible(true);

    $data = [
        'recipients' => [
            '233241234567',
            '233501234567',
        ],
        'message' => 'Test message',
    ];

    $result = $method->invoke($service, $data);

    expect($result['recipients'][0])->toBe('233****67');
    expect($result['recipients'][1])->toBe('233****67');
    expect($result['message'])->toBe('Test message');
});

test('sanitizeForLogging redacts clientSecret values', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('sanitizeForLogging');
    $method->setAccessible(true);

    $data = [
        'auth' => 'test_secret',
        'message' => 'Test message',
    ];

    $result = $method->invoke($service, $data);

    expect($result['auth'])->toBe('[REDACTED]');
    expect($result['message'])->toBe('Test message');
});

test('sanitizeForLogging preserves non-sensitive data', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('sanitizeForLogging');
    $method->setAccessible(true);

    $data = [
        'messageId' => 'msg_123',
        'status' => 'sent',
        'responseCode' => '0000',
    ];

    $result = $method->invoke($service, $data);

    expect($result['messageId'])->toBe('msg_123');
    expect($result['status'])->toBe('sent');
    expect($result['responseCode'])->toBe('0000');
});

// Edge case tests for sanitization (Task 4.4)

test('sanitizeForLogging handles deeply nested phone numbers', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('sanitizeForLogging');
    $method->setAccessible(true);

    $data = [
        'request' => [
            'recipients' => [
                'primary' => ['233241234567', '233501234567'],
                'secondary' => ['233301234567'],
            ],
        ],
        'message' => 'Test message',
    ];

    $result = $method->invoke($service, $data);

    expect($result['request']['recipients']['primary'][0])->toBe('233****67');
    expect($result['request']['recipients']['primary'][1])->toBe('233****67');
    expect($result['request']['recipients']['secondary'][0])->toBe('233****67');
    expect($result['message'])->toBe('Test message');
});

test('sanitizeForLogging handles mixed data types with phone numbers', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('sanitizeForLogging');
    $method->setAccessible(true);

    $data = [
        'phone' => '233241234567',
        'count' => 5,
        'active' => true,
        'metadata' => null,
        'tags' => ['urgent', 'customer'],
    ];

    $result = $method->invoke($service, $data);

    expect($result['phone'])->toBe('233****67');
    expect($result['count'])->toBe(5);
    expect($result['active'])->toBe(true);
    expect($result['metadata'])->toBeNull();
    expect($result['tags'])->toBe(['urgent', 'customer']);
});

test('sanitizeForLogging removes clientSecret with different key formats', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('sanitizeForLogging');
    $method->setAccessible(true);

    $data = [
        'clientSecret' => 'test_secret',
        'client_secret' => 'test_secret',
        'ClientSecret' => 'test_secret',
        'message' => 'Test message',
    ];

    $result = $method->invoke($service, $data);

    expect($result)->not->toHaveKey('clientSecret');
    expect($result)->not->toHaveKey('client_secret');
    expect($result)->not->toHaveKey('ClientSecret');
    expect($result['message'])->toBe('Test message');
});

test('sanitizeForLogging redacts clientSecret in nested structures', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('sanitizeForLogging');
    $method->setAccessible(true);

    $data = [
        'auth' => [
            'clientId' => 'test_client',
            'clientSecret' => 'test_secret',
            'token' => 'test_secret',
        ],
        'message' => 'Test message',
    ];

    $result = $method->invoke($service, $data);

    expect($result['auth'])->not->toHaveKey('clientSecret');
    expect($result['auth']['clientId'])->toBe('test_client');
    expect($result['auth']['token'])->toBe('[REDACTED]');
    expect($result['message'])->toBe('Test message');
});

test('sanitizeForLogging handles array with both phone numbers and clientSecret', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('sanitizeForLogging');
    $method->setAccessible(true);

    $data = [
        'recipients' => ['233241234567', '233501234567'],
        'clientSecret' => 'test_secret',
        'auth' => 'test_secret',
        'message' => 'Test message',
    ];

    $result = $method->invoke($service, $data);

    expect($result['recipients'][0])->toBe('233****67');
    expect($result['recipients'][1])->toBe('233****67');
    expect($result)->not->toHaveKey('clientSecret');
    expect($result['auth'])->toBe('[REDACTED]');
    expect($result['message'])->toBe('Test message');
});

test('sanitizeForLogging handles empty arrays', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('sanitizeForLogging');
    $method->setAccessible(true);

    $data = [
        'recipients' => [],
        'message' => 'Test message',
    ];

    $result = $method->invoke($service, $data);

    expect($result['recipients'])->toBe([]);
    expect($result['message'])->toBe('Test message');
});

test('sanitizeForLogging handles strings that look like phone numbers but are not', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('sanitizeForLogging');
    $method->setAccessible(true);

    $data = [
        'reference' => '123456789012', // 12 digits but doesn't start with 233
        'code' => '233123', // Starts with 233 but not 12 digits
        'phone' => '233241234567', // Valid phone number
    ];

    $result = $method->invoke($service, $data);

    expect($result['reference'])->toBe('123456789012'); // Not masked
    expect($result['code'])->toBe('233123'); // Not masked
    expect($result['phone'])->toBe('233****67'); // Masked
});

// Tests for parseResponse method (Task 6.1)

test('parseResponse extracts messageId, status, and responseCode from single SMS response', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a successful single SMS response
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageId' => 'msg_abc123',
        'status' => 'sent',
        'responseCode' => '0000',
    ]);

    $result = $method->invoke($service, $mockResponse);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('messageId');
    expect($result)->toHaveKey('status');
    expect($result)->toHaveKey('responseCode');
    expect($result['messageId'])->toBe('msg_abc123');
    expect($result['status'])->toBe('sent');
    expect($result['responseCode'])->toBe('0000');
});

test('parseResponse extracts messageIds, status, and responseCode from batch SMS response', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a successful batch SMS response
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageIds' => ['msg_abc123', 'msg_abc124', 'msg_abc125'],
        'status' => 'sent',
        'responseCode' => '0000',
    ]);

    $result = $method->invoke($service, $mockResponse);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('messageIds');
    expect($result)->toHaveKey('status');
    expect($result)->toHaveKey('responseCode');
    expect($result['messageIds'])->toBeArray();
    expect($result['messageIds'])->toHaveCount(3);
    expect($result['messageIds'][0])->toBe('msg_abc123');
    expect($result['status'])->toBe('sent');
    expect($result['responseCode'])->toBe('0000');
});

test('parseResponse throws exception when response is not an array', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response that returns non-array JSON
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn('invalid response');

    expect(fn () => $method->invoke($service, $mockResponse))
        ->toThrow(Exception::class, 'Invalid API response format');
});

test('parseResponse throws exception when messageId is missing status', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response with messageId but missing status
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageId' => 'msg_abc123',
        'responseCode' => '0000',
    ]);

    expect(fn () => $method->invoke($service, $mockResponse))
        ->toThrow(Exception::class, 'Invalid API response format');
});

test('parseResponse throws exception when messageId is missing responseCode', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response with messageId but missing responseCode
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageId' => 'msg_abc123',
        'status' => 'sent',
    ]);

    expect(fn () => $method->invoke($service, $mockResponse))
        ->toThrow(Exception::class, 'Invalid API response format');
});

test('parseResponse throws exception when messageIds is not an array', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response with messageIds as non-array
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageIds' => 'not_an_array',
        'status' => 'sent',
        'responseCode' => '0000',
    ]);

    expect(fn () => $method->invoke($service, $mockResponse))
        ->toThrow(Exception::class, 'Invalid API response format');
});

test('parseResponse throws exception when messageIds is missing status', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response with messageIds but missing status
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageIds' => ['msg_abc123'],
        'responseCode' => '0000',
    ]);

    expect(fn () => $method->invoke($service, $mockResponse))
        ->toThrow(Exception::class, 'Invalid API response format');
});

test('parseResponse throws exception when messageIds is missing responseCode', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response with messageIds but missing responseCode
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageIds' => ['msg_abc123'],
        'status' => 'sent',
    ]);

    expect(fn () => $method->invoke($service, $mockResponse))
        ->toThrow(Exception::class, 'Invalid API response format');
});

test('parseResponse throws exception when neither messageId nor messageIds is present', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response without messageId or messageIds
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'status' => 'sent',
        'responseCode' => '0000',
    ]);

    expect(fn () => $method->invoke($service, $mockResponse))
        ->toThrow(Exception::class, 'Invalid API response format');
});

test('parseResponse throws exception for empty response', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock an empty response
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([]);

    expect(fn () => $method->invoke($service, $mockResponse))
        ->toThrow(Exception::class, 'Invalid API response format');
});

test('parseResponse throws exception for null response', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a null response
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn(null);

    expect(fn () => $method->invoke($service, $mockResponse))
        ->toThrow(Exception::class, 'Invalid API response format');
});

test('parseResponse handles empty messageIds array', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response with empty messageIds array
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageIds' => [],
        'status' => 'sent',
        'responseCode' => '0000',
    ]);

    $result = $method->invoke($service, $mockResponse);

    expect($result)->toBeArray();
    expect($result['messageIds'])->toBeArray();
    expect($result['messageIds'])->toHaveCount(0);
    expect($result['status'])->toBe('sent');
    expect($result['responseCode'])->toBe('0000');
});

// Additional edge case tests for response parsing (Task 6.5)

test('parseResponse handles response with numeric messageId', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response with numeric messageId (as string)
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageId' => '1234567890',
        'status' => 'sent',
        'responseCode' => '0000',
    ]);

    $result = $method->invoke($service, $mockResponse);

    expect($result['messageId'])->toBe('1234567890');
    expect($result['status'])->toBe('sent');
    expect($result['responseCode'])->toBe('0000');
});

test('parseResponse handles response with special characters in messageId', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response with special characters in messageId
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageId' => 'msg-123_abc@test',
        'status' => 'sent',
        'responseCode' => '0000',
    ]);

    $result = $method->invoke($service, $mockResponse);

    expect($result['messageId'])->toBe('msg-123_abc@test');
    expect($result['status'])->toBe('sent');
    expect($result['responseCode'])->toBe('0000');
});

test('parseResponse handles response with unicode characters', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response with unicode characters
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageId' => 'msg_测试_🎉',
        'status' => 'envoyé',
        'responseCode' => '0000',
    ]);

    $result = $method->invoke($service, $mockResponse);

    expect($result['messageId'])->toBe('msg_测试_🎉');
    expect($result['status'])->toBe('envoyé');
    expect($result['responseCode'])->toBe('0000');
});

test('parseResponse handles batch response with single messageId', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a batch response with only one messageId
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageIds' => ['msg_abc123'],
        'status' => 'sent',
        'responseCode' => '0000',
    ]);

    $result = $method->invoke($service, $mockResponse);

    expect($result['messageIds'])->toBeArray();
    expect($result['messageIds'])->toHaveCount(1);
    expect($result['messageIds'][0])->toBe('msg_abc123');
    expect($result['status'])->toBe('sent');
    expect($result['responseCode'])->toBe('0000');
});

test('parseResponse handles batch response with many messageIds', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a batch response with many messageIds
    $messageIds = [];
    for ($i = 0; $i < 100; $i++) {
        $messageIds[] = 'msg_'.$i;
    }

    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageIds' => $messageIds,
        'status' => 'sent',
        'responseCode' => '0000',
    ]);

    $result = $method->invoke($service, $mockResponse);

    expect($result['messageIds'])->toBeArray();
    expect($result['messageIds'])->toHaveCount(100);
    expect($result['messageIds'][0])->toBe('msg_0');
    expect($result['messageIds'][99])->toBe('msg_99');
    expect($result['status'])->toBe('sent');
    expect($result['responseCode'])->toBe('0000');
});

test('parseResponse throws exception for response with only messageId field', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response with only messageId (missing status and responseCode)
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageId' => 'msg_abc123',
    ]);

    expect(fn () => $method->invoke($service, $mockResponse))
        ->toThrow(Exception::class, 'Invalid API response format');
});

test('parseResponse throws exception for response with only status field', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response with only status
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'status' => 'sent',
    ]);

    expect(fn () => $method->invoke($service, $mockResponse))
        ->toThrow(Exception::class, 'Invalid API response format');
});

test('parseResponse throws exception for response with only responseCode field', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response with only responseCode
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'responseCode' => '0000',
    ]);

    expect(fn () => $method->invoke($service, $mockResponse))
        ->toThrow(Exception::class, 'Invalid API response format');
});

test('parseResponse throws exception for boolean response', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response that returns boolean
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn(true);

    expect(fn () => $method->invoke($service, $mockResponse))
        ->toThrow(Exception::class, 'Invalid API response format');
});

test('parseResponse throws exception for numeric response', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response that returns number
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn(123);

    expect(fn () => $method->invoke($service, $mockResponse))
        ->toThrow(Exception::class, 'Invalid API response format');
});

test('parseResponse throws exception for string response', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response that returns string
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn('invalid response');

    expect(fn () => $method->invoke($service, $mockResponse))
        ->toThrow(Exception::class, 'Invalid API response format');
});

test('parseResponse handles response with extra fields', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response with extra fields
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageId' => 'msg_abc123',
        'status' => 'sent',
        'responseCode' => '0000',
        'extraField1' => 'value1',
        'extraField2' => 'value2',
        'metadata' => ['key' => 'value'],
    ]);

    $result = $method->invoke($service, $mockResponse);

    // Should only extract required fields
    expect($result)->toHaveCount(3);
    expect($result)->toHaveKey('messageId');
    expect($result)->toHaveKey('status');
    expect($result)->toHaveKey('responseCode');
    expect($result)->not->toHaveKey('extraField1');
    expect($result)->not->toHaveKey('extraField2');
    expect($result)->not->toHaveKey('metadata');
});

test('parseResponse handles batch response with duplicate messageIds', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a batch response with duplicate messageIds (unusual but valid)
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageIds' => ['msg_abc123', 'msg_abc123', 'msg_abc124'],
        'status' => 'sent',
        'responseCode' => '0000',
    ]);

    $result = $method->invoke($service, $mockResponse);

    expect($result['messageIds'])->toBeArray();
    expect($result['messageIds'])->toHaveCount(3);
    expect($result['messageIds'][0])->toBe('msg_abc123');
    expect($result['messageIds'][1])->toBe('msg_abc123');
    expect($result['messageIds'][2])->toBe('msg_abc124');
});

test('parseResponse handles response with different status values', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    $statuses = ['sent', 'pending', 'delivered', 'failed', 'queued', 'accepted', 'rejected'];

    foreach ($statuses as $status) {
        $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse->shouldReceive('json')->andReturn([
            'messageId' => 'msg_abc123',
            'status' => $status,
            'responseCode' => '0000',
        ]);

        $result = $method->invoke($service, $mockResponse);

        expect($result['status'])->toBe($status);
    }
});

test('parseResponse handles response with different responseCode values', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    $responseCodes = ['0000', '0001', '2001', '4000', '5000', '9999'];

    foreach ($responseCodes as $responseCode) {
        $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse->shouldReceive('json')->andReturn([
            'messageId' => 'msg_abc123',
            'status' => 'sent',
            'responseCode' => $responseCode,
        ]);

        $result = $method->invoke($service, $mockResponse);

        expect($result['responseCode'])->toBe($responseCode);
    }
});

test('parseResponse preserves messageId order in batch response', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a batch response with specific order
    $orderedIds = ['msg_3', 'msg_1', 'msg_5', 'msg_2', 'msg_4'];

    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageIds' => $orderedIds,
        'status' => 'sent',
        'responseCode' => '0000',
    ]);

    $result = $method->invoke($service, $mockResponse);

    // Verify order is preserved
    expect($result['messageIds'])->toBe($orderedIds);
    expect($result['messageIds'][0])->toBe('msg_3');
    expect($result['messageIds'][1])->toBe('msg_1');
    expect($result['messageIds'][2])->toBe('msg_5');
    expect($result['messageIds'][3])->toBe('msg_2');
    expect($result['messageIds'][4])->toBe('msg_4');
});

test('parseResponse handles response with whitespace in field values', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a response with whitespace in values
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageId' => '  msg_abc123  ',
        'status' => '  sent  ',
        'responseCode' => '  0000  ',
    ]);

    $result = $method->invoke($service, $mockResponse);

    // Values should be preserved as-is (including whitespace)
    expect($result['messageId'])->toBe('  msg_abc123  ');
    expect($result['status'])->toBe('  sent  ');
    expect($result['responseCode'])->toBe('  0000  ');
});

test('parseResponse handles batch response with mixed messageId formats', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Mock a batch response with various messageId formats
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageIds' => [
            'msg_abc123',
            '1234567890',
            'uuid-format-id',
            'special-chars-!@#',
            'unicode-测试',
        ],
        'status' => 'sent',
        'responseCode' => '0000',
    ]);

    $result = $method->invoke($service, $mockResponse);

    expect($result['messageIds'])->toBeArray();
    expect($result['messageIds'])->toHaveCount(5);
    expect($result['messageIds'][0])->toBe('msg_abc123');
    expect($result['messageIds'][1])->toBe('1234567890');
    expect($result['messageIds'][2])->toBe('uuid-format-id');
    expect($result['messageIds'][3])->toBe('special-chars-!@#');
    expect($result['messageIds'][4])->toBe('unicode-测试');
});

// Tests for sendSingle method (Task 7.1)

test('sendSingle sends SMS successfully and returns response', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    // Clear global fakes and set up specific fake for this test
    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response([
            'messageId' => 'msg_abc123',
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $service = new HubtelSmsService;
    $result = $service->sendSingle('233241234567', 'Test message');

    expect($result)->toBeArray();
    expect($result)->toHaveKey('messageId');
    expect($result)->toHaveKey('status');
    expect($result)->toHaveKey('responseCode');
    expect($result['messageId'])->toBe('msg_abc123');
    expect($result['status'])->toBe('sent');
    expect($result['responseCode'])->toBe('0000');
});

test('sendSingle validates configuration before sending', function () {
    config(['services.hubtel.client_id' => '']);
    config(['services.hubtel.client_secret' => '']);

    $service = new HubtelSmsService;

    expect(fn () => $service->sendSingle('233241234567', 'Test message'))
        ->toThrow(RuntimeException::class, 'Hubtel SMS is not properly configured');
});

test('sendSingle validates phone number before sending', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    expect(fn () => $service->sendSingle('invalid_phone', 'Test message'))
        ->toThrow(InvalidArgumentException::class, 'Invalid phone number format');
});

test('sendSingle sends correct request payload', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response([
            'messageId' => 'msg_abc123',
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $service = new HubtelSmsService;
    $service->sendSingle('233241234567', 'Test message');

    \Illuminate\Support\Facades\Http::assertSent(function ($request) {
        return $request->url() === 'https://sms.hubtel.com/v1/messages/send'
            && $request->hasHeader('Authorization')
            && $request['From'] === 'CediBites'
            && $request['To'] === '233241234567'
            && $request['Content'] === 'Test message';
    });
});

test('sendSingle includes Basic Auth header', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response([
            'messageId' => 'msg_abc123',
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $service = new HubtelSmsService;
    $service->sendSingle('233241234567', 'Test message');

    $expectedAuth = 'Basic '.base64_encode('test_client:test_secret');

    \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($expectedAuth) {
        return $request->hasHeader('Authorization', $expectedAuth);
    });
});

test('sendSingle throws exception on API error', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response([
            'message' => 'Invalid credentials',
            'responseCode' => '4001',
        ], 401),
    ]);

    $service = new HubtelSmsService;

    expect(fn () => $service->sendSingle('233241234567', 'Test message'))
        ->toThrow(Exception::class);
});

test('sendSingle logs error on API failure', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Log::spy();

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response([
            'message' => 'Invalid credentials',
            'responseCode' => '4001',
        ], 401),
    ]);

    $service = new HubtelSmsService;

    try {
        $service->sendSingle('233241234567', 'Test message');
    } catch (Exception $e) {
        // Expected exception
    }

    \Illuminate\Support\Facades\Log::shouldHaveReceived('error')
        ->once()
        ->with('Hubtel SMS API request failed', Mockery::on(function ($context) {
            return isset($context['endpoint'])
                && isset($context['status_code'])
                && $context['status_code'] === 401
                && isset($context['response']);
        }));
});

test('sendSingle logs success with sanitized data', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Log::spy();

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response([
            'messageId' => 'msg_abc123',
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $service = new HubtelSmsService;
    $service->sendSingle('233241234567', 'Test message');

    \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
        ->once()
        ->with('SMS sent successfully', Mockery::on(function ($context) {
            return isset($context['messageId'])
                && $context['messageId'] === 'msg_abc123'
                && isset($context['recipient_count'])
                && $context['recipient_count'] === 1
                && isset($context['to'])
                && $context['to'] === '233****67'
                && isset($context['timestamp']);
        }));
});

test('sendSingle throws exception on connection error', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
    });

    $service = new HubtelSmsService;

    expect(fn () => $service->sendSingle('233241234567', 'Test message'))
        ->toThrow(Exception::class, 'Failed to connect to Hubtel SMS API');
});

test('sendSingle logs connection error', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Log::spy();

    \Illuminate\Support\Facades\Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
    });

    $service = new HubtelSmsService;

    try {
        $service->sendSingle('233241234567', 'Test message');
    } catch (Exception $e) {
        // Expected exception
    }

    \Illuminate\Support\Facades\Log::shouldHaveReceived('error')
        ->once()
        ->with('Failed to connect to Hubtel SMS API', Mockery::on(function ($context) {
            return isset($context['endpoint'])
                && isset($context['error'])
                && str_contains($context['error'], 'Connection failed');
        }));
});

test('sendSingle handles empty message content', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response([
            'messageId' => 'msg_abc123',
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $service = new HubtelSmsService;
    $result = $service->sendSingle('233241234567', '');

    expect($result)->toBeArray();
    expect($result['messageId'])->toBe('msg_abc123');
});

test('sendSingle handles long message content', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response([
            'messageId' => 'msg_abc123',
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $longMessage = str_repeat('This is a test message. ', 50); // ~1200 characters

    $service = new HubtelSmsService;
    $result = $service->sendSingle('233241234567', $longMessage);

    expect($result)->toBeArray();
    expect($result['messageId'])->toBe('msg_abc123');

    \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($longMessage) {
        return $request['Content'] === $longMessage;
    });
});

test('sendSingle handles unicode message content', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response([
            'messageId' => 'msg_abc123',
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $unicodeMessage = 'Hello 你好 مرحبا 🎉';

    $service = new HubtelSmsService;
    $result = $service->sendSingle('233241234567', $unicodeMessage);

    expect($result)->toBeArray();
    expect($result['messageId'])->toBe('msg_abc123');

    \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($unicodeMessage) {
        return $request['Content'] === $unicodeMessage;
    });
});

test('sendSingle uses custom sender ID from config', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CustomSender']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response([
            'messageId' => 'msg_abc123',
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $service = new HubtelSmsService;
    $service->sendSingle('233241234567', 'Test message');

    \Illuminate\Support\Facades\Http::assertSent(function ($request) {
        return $request['From'] === 'CustomSender';
    });
});

test('sendSingle uses custom base URL from config', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://custom.api.com/v2/sms']);

    \Illuminate\Support\Facades\Http::fake([
        'https://custom.api.com/v2/sms/send' => \Illuminate\Support\Facades\Http::response([
            'messageId' => 'msg_abc123',
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $service = new HubtelSmsService;
    $result = $service->sendSingle('233241234567', 'Test message');

    expect($result)->toBeArray();
    expect($result['messageId'])->toBe('msg_abc123');

    \Illuminate\Support\Facades\Http::assertSent(function ($request) {
        return $request->url() === 'https://custom.api.com/v2/sms/send';
    });
});

test('sendSingle throws exception when response parsing fails', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response([
            'invalid' => 'response',
        ], 200),
    ]);

    $service = new HubtelSmsService;

    expect(fn () => $service->sendSingle('233241234567', 'Test message'))
        ->toThrow(Exception::class, 'Invalid API response format');
});

test('sendSingle handles different phone number formats', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response([
            'messageId' => 'msg_abc123',
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $validPhones = [
        '233241234567',
        '233501234567',
        '233301234567',
        '233200000000',
        '233999999999',
    ];

    $service = new HubtelSmsService;

    foreach ($validPhones as $phone) {
        $result = $service->sendSingle($phone, 'Test message');
        expect($result)->toBeArray();
        expect($result['messageId'])->toBe('msg_abc123');
    }
});

test('sendSingle preserves phone number as string', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response([
            'messageId' => 'msg_abc123',
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $service = new HubtelSmsService;
    $service->sendSingle('233000000000', 'Test message');

    \Illuminate\Support\Facades\Http::assertSent(function ($request) {
        return $request['To'] === '233000000000' && is_string($request['To']);
    });
});

// Tests for sendBatch method (Task 8.1)

test('sendBatch sends batch SMS successfully and returns response', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/batch/simple/send' => \Illuminate\Support\Facades\Http::response([
            'messageIds' => ['msg_abc123', 'msg_abc124'],
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $service = new HubtelSmsService;
    $result = $service->sendBatch(['233241234567', '233501234567'], 'Test message');

    expect($result)->toBeArray();
    expect($result)->toHaveKey('messageIds');
    expect($result)->toHaveKey('status');
    expect($result)->toHaveKey('responseCode');
    expect($result['messageIds'])->toBeArray();
    expect($result['messageIds'])->toHaveCount(2);
    expect($result['messageIds'][0])->toBe('msg_abc123');
    expect($result['messageIds'][1])->toBe('msg_abc124');
    expect($result['status'])->toBe('sent');
    expect($result['responseCode'])->toBe('0000');
});

test('sendBatch validates configuration before sending', function () {
    config(['services.hubtel.client_id' => '']);
    config(['services.hubtel.client_secret' => '']);

    $service = new HubtelSmsService;

    expect(fn () => $service->sendBatch(['233241234567'], 'Test message'))
        ->toThrow(RuntimeException::class, 'Hubtel SMS is not properly configured');
});

test('sendBatch validates all phone numbers before sending', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    expect(fn () => $service->sendBatch(['233241234567', 'invalid_phone'], 'Test message'))
        ->toThrow(InvalidArgumentException::class, 'Invalid phone number format');
});

test('sendBatch validates first phone number in array', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    expect(fn () => $service->sendBatch(['invalid_phone', '233241234567'], 'Test message'))
        ->toThrow(InvalidArgumentException::class, 'Invalid phone number format');
});

test('sendBatch validates middle phone number in array', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);

    $service = new HubtelSmsService;

    expect(fn () => $service->sendBatch(['233241234567', 'invalid', '233501234567'], 'Test message'))
        ->toThrow(InvalidArgumentException::class, 'Invalid phone number format');
});

test('sendBatch sends correct request payload', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/batch/simple/send' => \Illuminate\Support\Facades\Http::response([
            'messageIds' => ['msg_abc123', 'msg_abc124'],
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $recipients = ['233241234567', '233501234567'];
    $service = new HubtelSmsService;
    $service->sendBatch($recipients, 'Test message');

    \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($recipients) {
        return $request->url() === 'https://sms.hubtel.com/v1/messages/batch/simple/send'
            && $request->hasHeader('Authorization')
            && $request['From'] === 'CediBites'
            && $request['Recipients'] === $recipients
            && $request['Content'] === 'Test message';
    });
});

test('sendBatch includes Basic Auth header', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/batch/simple/send' => \Illuminate\Support\Facades\Http::response([
            'messageIds' => ['msg_abc123'],
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $service = new HubtelSmsService;
    $service->sendBatch(['233241234567'], 'Test message');

    $expectedAuth = 'Basic '.base64_encode('test_client:test_secret');

    \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($expectedAuth) {
        return $request->hasHeader('Authorization', $expectedAuth);
    });
});

test('sendBatch throws exception on API error', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/batch/simple/send' => \Illuminate\Support\Facades\Http::response([
            'message' => 'Invalid credentials',
            'responseCode' => '4001',
        ], 401),
    ]);

    $service = new HubtelSmsService;

    expect(fn () => $service->sendBatch(['233241234567'], 'Test message'))
        ->toThrow(Exception::class);
});

test('sendBatch logs error on API failure', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Log::spy();

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/batch/simple/send' => \Illuminate\Support\Facades\Http::response([
            'message' => 'Invalid credentials',
            'responseCode' => '4001',
        ], 401),
    ]);

    $service = new HubtelSmsService;

    try {
        $service->sendBatch(['233241234567'], 'Test message');
    } catch (Exception $e) {
        // Expected exception
    }

    \Illuminate\Support\Facades\Log::shouldHaveReceived('error')
        ->once()
        ->with('Hubtel SMS API request failed', Mockery::on(function ($context) {
            return isset($context['endpoint'])
                && str_contains($context['endpoint'], 'batch/simple/send')
                && isset($context['status_code'])
                && $context['status_code'] === 401
                && isset($context['response']);
        }));
});

test('sendBatch logs success with recipient count and sanitized data', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Log::spy();

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/batch/simple/send' => \Illuminate\Support\Facades\Http::response([
            'messageIds' => ['msg_abc123', 'msg_abc124'],
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $service = new HubtelSmsService;
    $service->sendBatch(['233241234567', '233501234567'], 'Test message');

    \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
        ->once()
        ->with('Batch SMS sent successfully', Mockery::on(function ($context) {
            return isset($context['messageIds_count'])
                && $context['messageIds_count'] === 2
                && isset($context['recipient_count'])
                && $context['recipient_count'] === 2
                && isset($context['recipients'])
                && is_array($context['recipients'])
                && $context['recipients'][0] === '233****67'
                && $context['recipients'][1] === '233****67'
                && isset($context['timestamp']);
        }));
});

test('sendBatch throws exception on connection error', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
    });

    $service = new HubtelSmsService;

    expect(fn () => $service->sendBatch(['233241234567'], 'Test message'))
        ->toThrow(Exception::class, 'Failed to connect to Hubtel SMS API');
});

test('sendBatch logs connection error', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Log::spy();

    \Illuminate\Support\Facades\Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
    });

    $service = new HubtelSmsService;

    try {
        $service->sendBatch(['233241234567'], 'Test message');
    } catch (Exception $e) {
        // Expected exception
    }

    \Illuminate\Support\Facades\Log::shouldHaveReceived('error')
        ->once()
        ->with('Failed to connect to Hubtel SMS API', Mockery::on(function ($context) {
            return isset($context['endpoint'])
                && str_contains($context['endpoint'], 'batch/simple/send')
                && isset($context['error'])
                && str_contains($context['error'], 'Connection failed');
        }));
});

test('sendBatch handles single recipient', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/batch/simple/send' => \Illuminate\Support\Facades\Http::response([
            'messageIds' => ['msg_abc123'],
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $service = new HubtelSmsService;
    $result = $service->sendBatch(['233241234567'], 'Test message');

    expect($result)->toBeArray();
    expect($result['messageIds'])->toHaveCount(1);
    expect($result['messageIds'][0])->toBe('msg_abc123');
});

test('sendBatch handles many recipients', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    $recipients = [];
    $messageIds = [];
    for ($i = 0; $i < 50; $i++) {
        $recipients[] = '233'.str_pad($i, 9, '0', STR_PAD_LEFT);
        $messageIds[] = 'msg_'.$i;
    }

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/batch/simple/send' => \Illuminate\Support\Facades\Http::response([
            'messageIds' => $messageIds,
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $service = new HubtelSmsService;
    $result = $service->sendBatch($recipients, 'Test message');

    expect($result)->toBeArray();
    expect($result['messageIds'])->toHaveCount(50);
    expect($result['messageIds'][0])->toBe('msg_0');
    expect($result['messageIds'][49])->toBe('msg_49');
});

test('sendBatch handles empty message content', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/batch/simple/send' => \Illuminate\Support\Facades\Http::response([
            'messageIds' => ['msg_abc123'],
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $service = new HubtelSmsService;
    $result = $service->sendBatch(['233241234567'], '');

    expect($result)->toBeArray();
    expect($result['messageIds'][0])->toBe('msg_abc123');
});

test('sendBatch handles unicode message content', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/batch/simple/send' => \Illuminate\Support\Facades\Http::response([
            'messageIds' => ['msg_abc123'],
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $unicodeMessage = 'Hello 你好 مرحبا 🎉';

    $service = new HubtelSmsService;
    $result = $service->sendBatch(['233241234567'], $unicodeMessage);

    expect($result)->toBeArray();
    expect($result['messageIds'][0])->toBe('msg_abc123');

    \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($unicodeMessage) {
        return $request['Content'] === $unicodeMessage;
    });
});

test('sendBatch uses custom sender ID from config', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CustomSender']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/batch/simple/send' => \Illuminate\Support\Facades\Http::response([
            'messageIds' => ['msg_abc123'],
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $service = new HubtelSmsService;
    $service->sendBatch(['233241234567'], 'Test message');

    \Illuminate\Support\Facades\Http::assertSent(function ($request) {
        return $request['From'] === 'CustomSender';
    });
});

test('sendBatch uses custom base URL from config', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://custom.api.com/v2/sms']);

    \Illuminate\Support\Facades\Http::fake([
        'https://custom.api.com/v2/sms/batch/simple/send' => \Illuminate\Support\Facades\Http::response([
            'messageIds' => ['msg_abc123'],
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $service = new HubtelSmsService;
    $result = $service->sendBatch(['233241234567'], 'Test message');

    expect($result)->toBeArray();
    expect($result['messageIds'][0])->toBe('msg_abc123');

    \Illuminate\Support\Facades\Http::assertSent(function ($request) {
        return $request->url() === 'https://custom.api.com/v2/sms/batch/simple/send';
    });
});

test('sendBatch throws exception when response parsing fails', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/batch/simple/send' => \Illuminate\Support\Facades\Http::response([
            'invalid' => 'response',
        ], 200),
    ]);

    $service = new HubtelSmsService;

    expect(fn () => $service->sendBatch(['233241234567'], 'Test message'))
        ->toThrow(Exception::class, 'Invalid API response format');
});

test('sendBatch preserves phone numbers as strings', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/batch/simple/send' => \Illuminate\Support\Facades\Http::response([
            'messageIds' => ['msg_abc123', 'msg_abc124'],
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $service = new HubtelSmsService;
    $service->sendBatch(['233000000000', '233000000001'], 'Test message');

    \Illuminate\Support\Facades\Http::assertSent(function ($request) {
        return $request['Recipients'][0] === '233000000000'
            && is_string($request['Recipients'][0])
            && $request['Recipients'][1] === '233000000001'
            && is_string($request['Recipients'][1]);
    });
});

test('sendBatch handles mixed valid phone number formats', function () {
    config(['services.hubtel.client_id' => 'test_client']);
    config(['services.hubtel.client_secret' => 'test_secret']);
    config(['services.hubtel.sender_id' => 'CediBites']);
    config(['services.hubtel.sms_base_url' => 'https://sms.hubtel.com/v1/messages']);

    \Illuminate\Support\Facades\Http::fake([
        'https://sms.hubtel.com/v1/messages/batch/simple/send' => \Illuminate\Support\Facades\Http::response([
            'messageIds' => ['msg_1', 'msg_2', 'msg_3', 'msg_4'],
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $recipients = [
        '233241234567',
        '233501234567',
        '233301234567',
        '233999999999',
    ];

    $service = new HubtelSmsService;
    $result = $service->sendBatch($recipients, 'Test message');

    expect($result)->toBeArray();
    expect($result['messageIds'])->toHaveCount(4);
});
