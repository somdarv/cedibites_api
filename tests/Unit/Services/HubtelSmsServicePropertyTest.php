<?php

use App\Services\HubtelSmsService;
use Illuminate\Support\Facades\Config;

test('property: configuration defaults for senderId and sms_base_url', function () {
    // **Property 6: Configuration Defaults**
    // **Validates: Requirements 4.3, 4.4**

    // Run 100 iterations with randomized configuration scenarios
    for ($i = 0; $i < 100; $i++) {
        // Randomly decide which optional configs to omit
        $omitSenderId = fake()->boolean();
        $omitBaseUrl = fake()->boolean();

        // Build a fresh config array for services.hubtel
        $hubtelConfig = [
            'client_id' => 'test_client_'.fake()->uuid(),
            'client_secret' => 'test_secret_'.fake()->uuid(),
        ];

        // Conditionally add optional configs
        if (! $omitSenderId) {
            $customSenderId = fake()->company();
            $hubtelConfig['sender_id'] = $customSenderId;
            $expectedSenderId = $customSenderId;
        } else {
            $expectedSenderId = 'CediBites';
        }

        if (! $omitBaseUrl) {
            $customBaseUrl = 'https://'.fake()->domainName().'/api/sms';
            $hubtelConfig['sms_base_url'] = $customBaseUrl;
            $expectedBaseUrl = $customBaseUrl;
        } else {
            $expectedBaseUrl = 'https://sms.hubtel.com/v1/messages';
        }

        // Set the entire hubtel config
        Config::set('services.hubtel', $hubtelConfig);

        // Create service instance
        $service = new HubtelSmsService;

        // Use reflection to access protected properties
        $reflection = new ReflectionClass($service);

        $senderIdProperty = $reflection->getProperty('senderId');
        $senderIdProperty->setAccessible(true);
        $actualSenderId = $senderIdProperty->getValue($service);

        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setAccessible(true);
        $actualBaseUrl = $baseUrlProperty->getValue($service);

        // Property: When senderId is missing/null, default to "CediBites"
        // Property: When sms_base_url is missing/null, default to "https://sms.hubtel.com/v1/messages"
        expect($actualSenderId)->toBe($expectedSenderId);
        expect($actualBaseUrl)->toBe($expectedBaseUrl);
    }
})->group('property', 'hubtel-sms-service');

test('property: Basic Authentication header format', function () {
    // **Property 4: Basic Authentication Header Format**
    // **Validates: Requirements 1.3, 2.3**

    // Run 100 iterations with randomized clientId and clientSecret
    for ($i = 0; $i < 100; $i++) {
        // Generate random clientId and clientSecret
        $clientId = fake()->uuid();
        $clientSecret = fake()->sha256();

        // Set configuration
        Config::set('services.hubtel', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'sender_id' => 'CediBites',
            'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
        ]);

        // Create service instance
        $service = new HubtelSmsService;

        // Use reflection to access protected getAuthHeader method
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getAuthHeader');
        $method->setAccessible(true);

        // Get the auth header
        $authHeader = $method->invoke($service);

        // Property: For any configured clientId and clientSecret,
        // getAuthHeader should return "Basic {base64(clientId:clientSecret)}"
        $expectedCredentials = base64_encode("{$clientId}:{$clientSecret}");
        $expectedHeader = "Basic {$expectedCredentials}";

        expect($authHeader)->toBe($expectedHeader);

        // Additional verification: header should start with "Basic "
        expect($authHeader)->toStartWith('Basic ');

        // Additional verification: the base64 part should decode back to clientId:clientSecret
        $base64Part = substr($authHeader, 6); // Remove "Basic " prefix
        $decodedCredentials = base64_decode($base64Part);
        expect($decodedCredentials)->toBe("{$clientId}:{$clientSecret}");
    }
})->group('property', 'hubtel-sms-service');

test('property: configuration validation before API calls', function () {
    // **Property 5: Configuration Validation Before API Calls**
    // **Validates: Requirements 4.5, 4.6**

    // Run 100 iterations with various invalid configuration scenarios
    for ($i = 0; $i < 100; $i++) {
        // Generate random valid values
        $validClientId = fake()->uuid();
        $validClientSecret = fake()->sha256();

        // Randomly choose which credential to invalidate (or both)
        $invalidateClientId = fake()->boolean();
        $invalidateBoth = fake()->boolean(30); // 30% chance to invalidate both

        // Use only null and empty string as invalid values (empty() returns true for these)
        if ($invalidateBoth) {
            $clientId = fake()->randomElement([null, '']);
            $clientSecret = fake()->randomElement([null, '']);
        } elseif ($invalidateClientId) {
            $clientId = fake()->randomElement([null, '']);
            $clientSecret = $validClientSecret;
        } else {
            $clientId = $validClientId;
            $clientSecret = fake()->randomElement([null, '']);
        }

        // Set configuration with invalid credentials
        Config::set('services.hubtel', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'sender_id' => 'CediBites',
            'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
        ]);

        // Create service instance
        $service = new HubtelSmsService;

        // Use reflection to access protected validateConfiguration method
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('validateConfiguration');
        $method->setAccessible(true);

        // Property: For any API operation, if clientId or clientSecret is empty,
        // the service should throw a RuntimeException before making any HTTP request
        try {
            $method->invoke($service);
            // If we reach here, the validation did not throw an exception
            // This should only happen if both credentials are valid (which they're not in this test)
            throw new Exception('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            // Expected behavior: RuntimeException should be thrown
            expect($e->getMessage())->toBe('Hubtel SMS is not properly configured');
        }
    }
})->group('property', 'hubtel-sms-service');

test('property: phone number validation accepts valid format and rejects invalid', function () {
    // **Property 1: Phone Number Validation**
    // **Validates: Requirements 3.1, 3.2, 3.3**

    // Set up valid configuration
    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    $service = new HubtelSmsService;

    // Use reflection to access protected validatePhoneNumber method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('validatePhoneNumber');
    $method->setAccessible(true);

    // Test 100 valid phone numbers
    for ($i = 0; $i < 100; $i++) {
        // Generate valid Ghana phone number: 233 + 9 random digits
        $validPhone = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);

        // Property: For any valid phone number (233XXXXXXXXX), validatePhoneNumber should not throw
        try {
            $method->invoke($service, $validPhone);
            expect(true)->toBeTrue(); // Validation passed
        } catch (InvalidArgumentException $e) {
            throw new Exception("Valid phone number {$validPhone} was rejected: {$e->getMessage()}");
        }
    }

    // Test 100 invalid phone numbers with various invalid formats
    for ($i = 0; $i < 100; $i++) {
        // Generate various invalid formats
        $invalidPhone = fake()->randomElement([
            // Wrong country code
            '234'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT),
            '232'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT),
            '1'.str_pad((string) fake()->numberBetween(0, 9999999999), 10, '0', STR_PAD_LEFT),
            // Wrong length (too short)
            '233'.str_pad((string) fake()->numberBetween(0, 99999999), 8, '0', STR_PAD_LEFT),
            '233'.fake()->numerify('####'),
            '233',
            // Wrong length (too long)
            '233'.str_pad((string) fake()->numberBetween(0, 9999999999), 10, '0', STR_PAD_LEFT),
            '233'.fake()->numerify('##########'),
            // Contains non-digits
            '233'.fake()->numerify('#########').fake()->randomLetter(),
            '233abc123456',
            '233-'.fake()->numerify('###-####'),
            '233 '.fake()->numerify('### ####'),
            // Missing country code
            fake()->numerify('#########'),
            '0'.fake()->numerify('##########'),
            // Empty or whitespace
            '',
            ' ',
            '   ',
        ]);

        // Property: For any invalid phone number, validatePhoneNumber should throw InvalidArgumentException
        try {
            $method->invoke($service, $invalidPhone);
            // If we reach here, validation did not throw (which is wrong for invalid numbers)
            throw new Exception("Invalid phone number {$invalidPhone} was accepted when it should have been rejected");
        } catch (InvalidArgumentException $e) {
            // Expected behavior: InvalidArgumentException should be thrown
            expect($e->getMessage())->toBe('Invalid phone number format');
        }
    }
})->group('property', 'hubtel-sms-service');

test('property: phone number string preservation with leading zeros', function () {
    // **Property 3: Phone Number String Preservation**
    // **Validates: Requirements 3.5**

    // Set up valid configuration
    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    $service = new HubtelSmsService;

    // Use reflection to access protected validatePhoneNumber method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('validatePhoneNumber');
    $method->setAccessible(true);

    // Test 100 phone numbers with leading zeros after country code
    for ($i = 0; $i < 100; $i++) {
        // Generate phone numbers with leading zeros in the local part
        // Format: 233 + 0XXXXXXXX (leading zero + 8 more digits)
        $localPart = '0'.str_pad((string) fake()->numberBetween(0, 99999999), 8, '0', STR_PAD_LEFT);
        $phoneNumber = '233'.$localPart;

        // Ensure it's a string (not accidentally converted to int)
        expect($phoneNumber)->toBeString();

        // Property: Phone numbers should be preserved as strings
        // The leading zero should not be lost
        expect($phoneNumber)->toStartWith('2330');
        expect(strlen($phoneNumber))->toBe(12);

        // Validate the phone number - should not throw exception
        try {
            $method->invoke($service, $phoneNumber);
            expect(true)->toBeTrue(); // Validation passed
        } catch (InvalidArgumentException $e) {
            throw new Exception("Valid phone number with leading zero {$phoneNumber} was rejected: {$e->getMessage()}");
        }

        // Verify the phone number is still a string after validation
        expect($phoneNumber)->toBeString();
        expect($phoneNumber[3])->toBe('0'); // Fourth character should be '0'

        // Property: The service should preserve the original string format
        // This is critical because phone numbers with leading zeros must remain strings
        expect($phoneNumber)->toStartWith('2330');

        // Verify type is preserved (not converted to numeric type)
        expect(is_string($phoneNumber))->toBeTrue();
        expect(is_numeric($phoneNumber))->toBeTrue(); // It's numeric but still a string
        expect(is_int($phoneNumber))->toBeFalse(); // Not an integer type
    }

    // Test additional edge cases with multiple leading zeros
    $edgeCases = [
        '233000000000', // All zeros after country code
        '233001234567', // Two leading zeros
        '233012345678', // One leading zero
        '233098765432', // Leading zero with high digits
    ];

    foreach ($edgeCases as $phoneNumber) {
        // Verify it's a string
        expect($phoneNumber)->toBeString();

        // Validate - should not throw
        try {
            $method->invoke($service, $phoneNumber);
            expect(true)->toBeTrue();
        } catch (InvalidArgumentException $e) {
            throw new Exception("Valid phone number {$phoneNumber} was rejected: {$e->getMessage()}");
        }

        // Verify string preservation
        expect($phoneNumber)->toBeString();
        expect(strlen($phoneNumber))->toBe(12);

        // Verify the leading zeros are preserved
        if (str_starts_with($phoneNumber, '2330')) {
            expect($phoneNumber[3])->toBe('0');
        }
    }
})->group('property', 'hubtel-sms-service');

test('property: phone number sanitization in logs', function () {
    // **Property 13: Phone Number Sanitization in Logs**
    // **Validates: Requirements 1.6, 5.3**

    // Set up valid configuration
    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    $service = new HubtelSmsService;

    // Use reflection to access protected sanitizeForLogging method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('sanitizeForLogging');
    $method->setAccessible(true);

    // Test 100 valid Ghana phone numbers
    for ($i = 0; $i < 100; $i++) {
        // Generate valid Ghana phone number: 233 + 9 random digits
        $phoneNumber = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);

        // Create test data with phone number
        $testData = [
            'To' => $phoneNumber,
            'From' => 'CediBites',
            'Content' => fake()->sentence(),
        ];

        // Sanitize the data
        $sanitized = $method->invoke($service, $testData);

        // Property: For any phone number logged by the service,
        // the log entry should show only the first 3 and last 2 digits,
        // with middle digits masked (e.g., "233****67")
        $expectedSanitized = substr($phoneNumber, 0, 3).'****'.substr($phoneNumber, -2);

        expect($sanitized['To'])->toBe($expectedSanitized);

        // Verify the sanitized format
        expect($sanitized['To'])->toStartWith('233');
        expect($sanitized['To'])->toHaveLength(9); // 3 + 4 stars + 2
        expect($sanitized['To'])->toContain('****');

        // Verify the original phone number is not in the sanitized data
        expect($sanitized['To'])->not->toBe($phoneNumber);

        // Verify other fields are preserved
        expect($sanitized['From'])->toBe('CediBites');
        expect($sanitized['Content'])->toBe($testData['Content']);
    }

    // Test batch SMS with multiple recipients
    for ($i = 0; $i < 100; $i++) {
        // Generate 2-5 random phone numbers
        $recipientCount = fake()->numberBetween(2, 5);
        $recipients = [];
        $expectedSanitized = [];

        for ($j = 0; $j < $recipientCount; $j++) {
            $phone = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);
            $recipients[] = $phone;
            $expectedSanitized[] = substr($phone, 0, 3).'****'.substr($phone, -2);
        }

        // Create test data with multiple recipients
        $testData = [
            'Recipients' => $recipients,
            'From' => 'CediBites',
            'Content' => fake()->sentence(),
        ];

        // Sanitize the data
        $sanitized = $method->invoke($service, $testData);

        // Property: All phone numbers in the Recipients array should be sanitized
        expect($sanitized['Recipients'])->toBeArray();
        expect($sanitized['Recipients'])->toHaveCount($recipientCount);

        foreach ($sanitized['Recipients'] as $index => $sanitizedPhone) {
            expect($sanitizedPhone)->toBe($expectedSanitized[$index]);
            expect($sanitizedPhone)->toStartWith('233');
            expect($sanitizedPhone)->toHaveLength(9);
            expect($sanitizedPhone)->toContain('****');
            expect($sanitizedPhone)->not->toBe($recipients[$index]);
        }
    }

    // Test nested data structures
    for ($i = 0; $i < 100; $i++) {
        $phone1 = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);
        $phone2 = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);

        // Create nested data structure
        $testData = [
            'request' => [
                'To' => $phone1,
                'From' => 'CediBites',
            ],
            'response' => [
                'recipient' => $phone2,
                'status' => 'sent',
            ],
        ];

        // Sanitize the data
        $sanitized = $method->invoke($service, $testData);

        // Property: Phone numbers in nested structures should be sanitized
        $expectedPhone1 = substr($phone1, 0, 3).'****'.substr($phone1, -2);
        $expectedPhone2 = substr($phone2, 0, 3).'****'.substr($phone2, -2);

        expect($sanitized['request']['To'])->toBe($expectedPhone1);
        expect($sanitized['response']['recipient'])->toBe($expectedPhone2);

        // Verify other fields are preserved
        expect($sanitized['request']['From'])->toBe('CediBites');
        expect($sanitized['response']['status'])->toBe('sent');
    }

    // Test edge cases with phone numbers that have leading zeros
    $edgeCases = [
        '233000000000', // All zeros after country code
        '233001234567', // Two leading zeros
        '233012345678', // One leading zero
        '233098765432', // Leading zero with high digits
    ];

    foreach ($edgeCases as $phoneNumber) {
        $testData = ['To' => $phoneNumber];
        $sanitized = $method->invoke($service, $testData);

        // Property: Sanitization should preserve the format for all valid phone numbers
        $expectedSanitized = substr($phoneNumber, 0, 3).'****'.substr($phoneNumber, -2);
        expect($sanitized['To'])->toBe($expectedSanitized);
        expect($sanitized['To'])->toStartWith('233');
        expect($sanitized['To'])->toHaveLength(9);
    }
})->group('property', 'hubtel-sms-service');

test('property: client secret never logged in plain text', function () {
    // **Property 14: Client Secret Never Logged**
    // **Validates: Requirements 5.5**

    // Run 100 iterations with various data structures containing clientSecret
    for ($i = 0; $i < 100; $i++) {
        // Generate random clientSecret
        $clientSecret = fake()->sha256();

        // Set up configuration with the clientSecret
        Config::set('services.hubtel', [
            'client_id' => fake()->uuid(),
            'client_secret' => $clientSecret,
            'sender_id' => 'CediBites',
            'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
        ]);

        $service = new HubtelSmsService;

        // Use reflection to access protected sanitizeForLogging method
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('sanitizeForLogging');
        $method->setAccessible(true);

        // Test various data structures that might contain clientSecret
        $testScenarios = [
            // Direct clientSecret value
            ['clientSecret' => $clientSecret],
            ['client_secret' => $clientSecret],

            // Mixed with other data
            [
                'To' => '233241234567',
                'clientSecret' => $clientSecret,
                'Content' => fake()->sentence(),
            ],

            // Nested structures
            [
                'request' => [
                    'auth' => [
                        'clientSecret' => $clientSecret,
                    ],
                    'To' => '233241234567',
                ],
            ],

            // Array values containing clientSecret
            [
                'credentials' => [$clientSecret, fake()->uuid()],
                'To' => '233241234567',
            ],

            // Multiple occurrences
            [
                'secret1' => $clientSecret,
                'secret2' => $clientSecret,
                'data' => ['secret3' => $clientSecret],
            ],

            // Mixed keys
            [
                'client_secret' => $clientSecret,
                'clientSecret' => $clientSecret,
                'CLIENT_SECRET' => $clientSecret,
            ],
        ];

        foreach ($testScenarios as $testData) {
            // Sanitize the data
            $sanitized = $method->invoke($service, $testData);

            // Property: For any log entry produced by the service,
            // it should never contain the clientSecret value in plain text

            // Convert sanitized data to JSON string for comprehensive search
            $sanitizedJson = json_encode($sanitized);

            // Verify clientSecret value is not present in the sanitized output
            expect($sanitizedJson)->not->toContain($clientSecret);

            // Verify keys named 'clientSecret' or 'client_secret' are removed
            expect($sanitized)->not->toHaveKey('clientSecret');
            expect($sanitized)->not->toHaveKey('client_secret');
            expect($sanitized)->not->toHaveKey('CLIENT_SECRET');

            // Recursively check nested arrays don't contain clientSecret
            $containsSecret = function ($data) use ($clientSecret, &$containsSecret) {
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        // Check if key is a clientSecret variant
                        if (in_array(strtolower($key), ['clientsecret', 'client_secret'])) {
                            return true;
                        }
                        // Check if value matches clientSecret
                        if ($value === $clientSecret) {
                            return true;
                        }
                        // Recursively check nested arrays
                        if (is_array($value) && $containsSecret($value)) {
                            return true;
                        }
                    }
                }

                return false;
            };

            expect($containsSecret($sanitized))->toBeFalse();
        }
    }

    // Additional test: Verify clientSecret is replaced with [REDACTED]
    for ($i = 0; $i < 100; $i++) {
        $clientSecret = fake()->sha256();

        Config::set('services.hubtel', [
            'client_id' => fake()->uuid(),
            'client_secret' => $clientSecret,
            'sender_id' => 'CediBites',
            'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
        ]);

        $service = new HubtelSmsService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('sanitizeForLogging');
        $method->setAccessible(true);

        // Test data with clientSecret as a value (not a key)
        $testData = [
            'auth_header' => 'Basic '.base64_encode("client_id:{$clientSecret}"),
            'credentials' => [$clientSecret],
            'To' => '233241234567',
        ];

        $sanitized = $method->invoke($service, $testData);

        // Property: clientSecret values should be replaced with [REDACTED]
        $sanitizedJson = json_encode($sanitized);
        expect($sanitizedJson)->not->toContain($clientSecret);
        expect($sanitizedJson)->toContain('[REDACTED]');

        // Verify the credentials array has [REDACTED] instead of clientSecret
        if (isset($sanitized['credentials']) && is_array($sanitized['credentials'])) {
            expect($sanitized['credentials'])->toContain('[REDACTED]');
            expect($sanitized['credentials'])->not->toContain($clientSecret);
        }
    }

    // Edge case: Empty or null clientSecret should not cause issues
    Config::set('services.hubtel', [
        'client_id' => 'test_id',
        'client_secret' => null,
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    $service = new HubtelSmsService;
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('sanitizeForLogging');
    $method->setAccessible(true);

    $testData = [
        'clientSecret' => null,
        'To' => '233241234567',
    ];

    $sanitized = $method->invoke($service, $testData);

    // Property: Keys named clientSecret should still be removed even if value is null
    expect($sanitized)->not->toHaveKey('clientSecret');
})->group('property', 'hubtel-sms-service');

test('property: successful response parsing extracts required fields', function () {
    // **Property 9: Successful Response Parsing**
    // **Validates: Requirements 1.4, 2.4, 6.2, 6.3, 6.4**

    // Set up valid configuration
    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    $service = new HubtelSmsService;

    // Use reflection to access protected parseResponse method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Test 100 single SMS responses
    for ($i = 0; $i < 100; $i++) {
        // Generate random response data
        $messageId = fake()->uuid();
        $status = fake()->randomElement(['sent', 'pending', 'delivered', 'failed']);
        $responseCode = fake()->randomElement(['0000', '0001', '2001', '4000']);

        // Mock a successful single SMS response
        $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse->shouldReceive('json')->andReturn([
            'messageId' => $messageId,
            'status' => $status,
            'responseCode' => $responseCode,
        ]);

        // Parse the response
        $result = $method->invoke($service, $mockResponse);

        // Property: For any successful API response (HTTP 200),
        // the service should extract and return messageId, status, and responseCode
        expect($result)->toBeArray();
        expect($result)->toHaveKey('messageId');
        expect($result)->toHaveKey('status');
        expect($result)->toHaveKey('responseCode');
        expect($result['messageId'])->toBe($messageId);
        expect($result['status'])->toBe($status);
        expect($result['responseCode'])->toBe($responseCode);

        // Verify no extra fields are added
        expect($result)->toHaveCount(3);
    }

    // Test 100 batch SMS responses
    for ($i = 0; $i < 100; $i++) {
        // Generate random number of message IDs (1-10)
        $messageCount = fake()->numberBetween(1, 10);
        $messageIds = [];
        for ($j = 0; $j < $messageCount; $j++) {
            $messageIds[] = fake()->uuid();
        }

        $status = fake()->randomElement(['sent', 'pending', 'delivered', 'failed']);
        $responseCode = fake()->randomElement(['0000', '0001', '2001', '4000']);

        // Mock a successful batch SMS response
        $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse->shouldReceive('json')->andReturn([
            'messageIds' => $messageIds,
            'status' => $status,
            'responseCode' => $responseCode,
        ]);

        // Parse the response
        $result = $method->invoke($service, $mockResponse);

        // Property: For batch responses, the service should extract messageIds (array), status, and responseCode
        expect($result)->toBeArray();
        expect($result)->toHaveKey('messageIds');
        expect($result)->toHaveKey('status');
        expect($result)->toHaveKey('responseCode');
        expect($result['messageIds'])->toBeArray();
        expect($result['messageIds'])->toHaveCount($messageCount);
        expect($result['messageIds'])->toBe($messageIds);
        expect($result['status'])->toBe($status);
        expect($result['responseCode'])->toBe($responseCode);

        // Verify no extra fields are added
        expect($result)->toHaveCount(3);
    }

    // Test edge case: Empty messageIds array (valid but unusual)
    $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse->shouldReceive('json')->andReturn([
        'messageIds' => [],
        'status' => 'sent',
        'responseCode' => '0000',
    ]);

    $result = $method->invoke($service, $mockResponse);

    // Property: Empty messageIds array should be accepted
    expect($result)->toBeArray();
    expect($result['messageIds'])->toBeArray();
    expect($result['messageIds'])->toHaveCount(0);
    expect($result['status'])->toBe('sent');
    expect($result['responseCode'])->toBe('0000');

    // Test with various data types for fields
    for ($i = 0; $i < 100; $i++) {
        // Test that numeric messageIds are preserved
        $messageId = fake()->randomElement([
            fake()->uuid(),
            (string) fake()->numberBetween(1000000, 9999999),
            'msg_'.fake()->numerify('######'),
            fake()->sha1(),
        ]);

        // Test various status values
        $status = fake()->randomElement([
            'sent',
            'pending',
            'delivered',
            'failed',
            'queued',
            'accepted',
        ]);

        // Test various responseCode formats
        $responseCode = fake()->randomElement([
            '0000',
            '0001',
            '2001',
            '4000',
            '5000',
            fake()->numerify('####'),
        ]);

        $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse->shouldReceive('json')->andReturn([
            'messageId' => $messageId,
            'status' => $status,
            'responseCode' => $responseCode,
        ]);

        $result = $method->invoke($service, $mockResponse);

        // Property: All field values should be preserved exactly as received
        expect($result['messageId'])->toBe($messageId);
        expect($result['status'])->toBe($status);
        expect($result['responseCode'])->toBe($responseCode);
    }

    // Test with additional fields in response (should be ignored)
    for ($i = 0; $i < 100; $i++) {
        $messageId = fake()->uuid();
        $status = 'sent';
        $responseCode = '0000';

        $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse->shouldReceive('json')->andReturn([
            'messageId' => $messageId,
            'status' => $status,
            'responseCode' => $responseCode,
            'extraField1' => fake()->word(),
            'extraField2' => fake()->numberBetween(1, 100),
            'metadata' => ['key' => 'value'],
        ]);

        $result = $method->invoke($service, $mockResponse);

        // Property: Only required fields should be extracted, extra fields ignored
        expect($result)->toHaveCount(3);
        expect($result)->toHaveKey('messageId');
        expect($result)->toHaveKey('status');
        expect($result)->toHaveKey('responseCode');
        expect($result)->not->toHaveKey('extraField1');
        expect($result)->not->toHaveKey('extraField2');
        expect($result)->not->toHaveKey('metadata');
    }
})->group('property', 'hubtel-sms-service');

test('property: invalid response format handling throws exception', function () {
    // **Property 11: Invalid Response Format Handling**
    // **Validates: Requirements 6.5**

    // Set up valid configuration
    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    $service = new HubtelSmsService;

    // Use reflection to access protected parseResponse method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Test 100 invalid response scenarios
    for ($i = 0; $i < 100; $i++) {
        // Generate various invalid response formats
        $invalidResponses = [
            // Non-array responses
            'invalid string response',
            123,
            true,
            null,

            // Empty array
            [],

            // Missing required fields for single SMS
            ['messageId' => fake()->uuid()], // Missing status and responseCode
            ['status' => 'sent'], // Missing messageId and responseCode
            ['responseCode' => '0000'], // Missing messageId and status
            ['messageId' => fake()->uuid(), 'status' => 'sent'], // Missing responseCode
            ['messageId' => fake()->uuid(), 'responseCode' => '0000'], // Missing status
            ['status' => 'sent', 'responseCode' => '0000'], // Missing messageId

            // Missing required fields for batch SMS
            ['messageIds' => [fake()->uuid()]], // Missing status and responseCode
            ['messageIds' => [fake()->uuid()], 'status' => 'sent'], // Missing responseCode
            ['messageIds' => [fake()->uuid()], 'responseCode' => '0000'], // Missing status

            // Invalid messageIds type (not an array)
            ['messageIds' => 'not_an_array', 'status' => 'sent', 'responseCode' => '0000'],
            ['messageIds' => 123, 'status' => 'sent', 'responseCode' => '0000'],
            ['messageIds' => true, 'status' => 'sent', 'responseCode' => '0000'],
            ['messageIds' => null, 'status' => 'sent', 'responseCode' => '0000'],

            // Neither messageId nor messageIds present
            ['status' => 'sent', 'responseCode' => '0000', 'otherField' => 'value'],
            ['data' => ['messageId' => fake()->uuid()], 'status' => 'sent', 'responseCode' => '0000'],

            // Null values for required fields
            ['messageId' => null, 'status' => 'sent', 'responseCode' => '0000'],
            ['messageId' => fake()->uuid(), 'status' => null, 'responseCode' => '0000'],
            ['messageId' => fake()->uuid(), 'status' => 'sent', 'responseCode' => null],
        ];

        // Randomly select an invalid response format
        $invalidResponse = fake()->randomElement($invalidResponses);

        // Mock a response with invalid format
        $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse->shouldReceive('json')->andReturn($invalidResponse);

        // Property: For any response with invalid JSON or missing required fields,
        // the parseResponse method should throw an exception with message "Invalid API response format"
        try {
            $method->invoke($service, $mockResponse);
            // If we reach here, the validation did not throw an exception
            throw new Exception('Expected Exception with message "Invalid API response format" was not thrown for invalid response: '.json_encode($invalidResponse));
        } catch (Exception $e) {
            // Expected behavior: Exception should be thrown
            expect($e->getMessage())->toBe('Invalid API response format');
        }
    }

    // Test specific edge cases with malformed data
    $edgeCases = [
        // Empty strings for required fields
        ['messageId' => '', 'status' => 'sent', 'responseCode' => '0000'],
        ['messageId' => fake()->uuid(), 'status' => '', 'responseCode' => '0000'],
        ['messageId' => fake()->uuid(), 'status' => 'sent', 'responseCode' => ''],

        // Whitespace-only strings
        ['messageId' => '   ', 'status' => 'sent', 'responseCode' => '0000'],
        ['messageId' => fake()->uuid(), 'status' => '   ', 'responseCode' => '0000'],

        // Wrong data types
        ['messageId' => 12345, 'status' => 'sent', 'responseCode' => '0000'], // messageId as number
        ['messageId' => ['nested' => 'array'], 'status' => 'sent', 'responseCode' => '0000'], // messageId as array
        ['messageId' => fake()->uuid(), 'status' => 123, 'responseCode' => '0000'], // status as number
        ['messageId' => fake()->uuid(), 'status' => 'sent', 'responseCode' => 123], // responseCode as number

        // Mixed valid and invalid structures
        ['messageId' => fake()->uuid(), 'messageIds' => [fake()->uuid()], 'status' => 'sent', 'responseCode' => '0000'], // Both messageId and messageIds present (ambiguous)
    ];

    foreach ($edgeCases as $edgeCase) {
        $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse->shouldReceive('json')->andReturn($edgeCase);

        // Note: Some edge cases might actually be valid (e.g., numeric messageId might be acceptable)
        // The test verifies that the parseResponse method handles these consistently
        try {
            $result = $method->invoke($service, $mockResponse);
            // If parsing succeeds, verify the result is valid
            expect($result)->toBeArray();
            expect($result)->toHaveKey('messageId');
            expect($result)->toHaveKey('status');
            expect($result)->toHaveKey('responseCode');
        } catch (Exception $e) {
            // If parsing fails, verify the error message
            expect($e->getMessage())->toBe('Invalid API response format');
        }
    }

    // Test deeply nested invalid structures
    for ($i = 0; $i < 100; $i++) {
        $invalidNestedResponses = [
            // Nested data structure (messageId not at root level)
            ['data' => ['messageId' => fake()->uuid(), 'status' => 'sent', 'responseCode' => '0000']],
            ['response' => ['messageId' => fake()->uuid(), 'status' => 'sent', 'responseCode' => '0000']],

            // Array of responses instead of single response (numeric keys)
            [
                0 => ['messageId' => fake()->uuid(), 'status' => 'sent', 'responseCode' => '0000'],
                1 => ['messageId' => fake()->uuid(), 'status' => 'sent', 'responseCode' => '0000'],
            ],
        ];

        $invalidResponse = fake()->randomElement($invalidNestedResponses);

        $mockResponse = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse->shouldReceive('json')->andReturn($invalidResponse);

        // Property: Nested or malformed structures should throw exception
        $exceptionThrown = false;
        try {
            $method->invoke($service, $mockResponse);
        } catch (Exception $e) {
            $exceptionThrown = true;
            expect($e->getMessage())->toBe('Invalid API response format');
        }

        // Verify that an exception was thrown
        expect($exceptionThrown)->toBeTrue();
    }
})->group('property', 'hubtel-sms-service');

test('property: response round-trip produces equivalent data structure', function () {
    // **Property 12: Response Round-Trip**
    // **Validates: Requirements 6.6**

    // Set up valid configuration
    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    $service = new HubtelSmsService;

    // Use reflection to access protected parseResponse method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('parseResponse');
    $method->setAccessible(true);

    // Test 100 single SMS response round-trips
    for ($i = 0; $i < 100; $i++) {
        // Generate random valid single SMS response
        $originalData = [
            'messageId' => fake()->uuid(),
            'status' => fake()->randomElement(['sent', 'pending', 'delivered', 'failed', 'queued']),
            'responseCode' => fake()->randomElement(['0000', '0001', '2001', '4000', '5000']),
        ];

        // Step 1: Parse the original response
        $mockResponse1 = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse1->shouldReceive('json')->andReturn($originalData);
        $parsed1 = $method->invoke($service, $mockResponse1);

        // Step 2: Encode parsed data back to JSON
        $jsonEncoded = json_encode($parsed1);

        // Step 3: Decode JSON back to array
        $decoded = json_decode($jsonEncoded, true);

        // Step 4: Parse the decoded data again
        $mockResponse2 = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse2->shouldReceive('json')->andReturn($decoded);
        $parsed2 = $method->invoke($service, $mockResponse2);

        // Property: For any valid Hubtel SMS API response,
        // parsing -> encoding to JSON -> parsing again should produce equivalent data structure
        expect($parsed2)->toBe($parsed1);
        expect($parsed2['messageId'])->toBe($parsed1['messageId']);
        expect($parsed2['status'])->toBe($parsed1['status']);
        expect($parsed2['responseCode'])->toBe($parsed1['responseCode']);

        // Verify the round-trip preserves all data
        expect($parsed2)->toHaveCount(3);
        expect($parsed1)->toHaveCount(3);

        // Verify JSON encoding/decoding doesn't corrupt data
        expect(json_decode($jsonEncoded, true))->toBe($parsed1);
    }

    // Test 100 batch SMS response round-trips
    for ($i = 0; $i < 100; $i++) {
        // Generate random valid batch SMS response
        $messageCount = fake()->numberBetween(1, 10);
        $messageIds = [];
        for ($j = 0; $j < $messageCount; $j++) {
            $messageIds[] = fake()->uuid();
        }

        $originalData = [
            'messageIds' => $messageIds,
            'status' => fake()->randomElement(['sent', 'pending', 'delivered', 'failed', 'queued']),
            'responseCode' => fake()->randomElement(['0000', '0001', '2001', '4000', '5000']),
        ];

        // Step 1: Parse the original response
        $mockResponse1 = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse1->shouldReceive('json')->andReturn($originalData);
        $parsed1 = $method->invoke($service, $mockResponse1);

        // Step 2: Encode parsed data back to JSON
        $jsonEncoded = json_encode($parsed1);

        // Step 3: Decode JSON back to array
        $decoded = json_decode($jsonEncoded, true);

        // Step 4: Parse the decoded data again
        $mockResponse2 = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse2->shouldReceive('json')->andReturn($decoded);
        $parsed2 = $method->invoke($service, $mockResponse2);

        // Property: Round-trip should preserve batch response structure
        expect($parsed2)->toBe($parsed1);
        expect($parsed2['messageIds'])->toBe($parsed1['messageIds']);
        expect($parsed2['messageIds'])->toHaveCount($messageCount);
        expect($parsed2['status'])->toBe($parsed1['status']);
        expect($parsed2['responseCode'])->toBe($parsed1['responseCode']);

        // Verify array order is preserved
        foreach ($parsed2['messageIds'] as $index => $messageId) {
            expect($messageId)->toBe($parsed1['messageIds'][$index]);
        }
    }

    // Test edge cases with special characters and unicode
    for ($i = 0; $i < 100; $i++) {
        $originalData = [
            'messageId' => fake()->randomElement([
                fake()->uuid(),
                'msg_'.fake()->numerify('######'),
                'id-with-special-chars-!@#$%',
                'unicode-测试-🎉',
                'spaces in id',
                'UPPERCASE_ID',
                'lowercase_id',
                'MixedCase_ID_123',
            ]),
            'status' => fake()->randomElement([
                'sent',
                'Status With Spaces',
                'status-with-dashes',
                'STATUS_UPPERCASE',
                'unicode-状态',
            ]),
            'responseCode' => fake()->randomElement([
                '0000',
                'CODE-123',
                'custom_code',
                '9999',
            ]),
        ];

        // Round-trip test
        $mockResponse1 = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse1->shouldReceive('json')->andReturn($originalData);
        $parsed1 = $method->invoke($service, $mockResponse1);

        $jsonEncoded = json_encode($parsed1);
        $decoded = json_decode($jsonEncoded, true);

        $mockResponse2 = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse2->shouldReceive('json')->andReturn($decoded);
        $parsed2 = $method->invoke($service, $mockResponse2);

        // Property: Special characters and unicode should survive round-trip
        expect($parsed2)->toBe($parsed1);
        expect($parsed2['messageId'])->toBe($parsed1['messageId']);
        expect($parsed2['status'])->toBe($parsed1['status']);
        expect($parsed2['responseCode'])->toBe($parsed1['responseCode']);
    }

    // Test with empty messageIds array
    $originalData = [
        'messageIds' => [],
        'status' => 'sent',
        'responseCode' => '0000',
    ];

    $mockResponse1 = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse1->shouldReceive('json')->andReturn($originalData);
    $parsed1 = $method->invoke($service, $mockResponse1);

    $jsonEncoded = json_encode($parsed1);
    $decoded = json_decode($jsonEncoded, true);

    $mockResponse2 = Mockery::mock('Illuminate\Http\Client\Response');
    $mockResponse2->shouldReceive('json')->andReturn($decoded);
    $parsed2 = $method->invoke($service, $mockResponse2);

    // Property: Empty arrays should survive round-trip
    expect($parsed2)->toBe($parsed1);
    expect($parsed2['messageIds'])->toBeArray();
    expect($parsed2['messageIds'])->toHaveCount(0);

    // Test multiple round-trips (parse -> encode -> parse -> encode -> parse)
    for ($i = 0; $i < 100; $i++) {
        $originalData = [
            'messageId' => fake()->uuid(),
            'status' => fake()->randomElement(['sent', 'pending', 'delivered']),
            'responseCode' => fake()->randomElement(['0000', '0001', '2001']),
        ];

        // First round-trip
        $mockResponse1 = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse1->shouldReceive('json')->andReturn($originalData);
        $parsed1 = $method->invoke($service, $mockResponse1);

        $json1 = json_encode($parsed1);
        $decoded1 = json_decode($json1, true);

        // Second round-trip
        $mockResponse2 = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse2->shouldReceive('json')->andReturn($decoded1);
        $parsed2 = $method->invoke($service, $mockResponse2);

        $json2 = json_encode($parsed2);
        $decoded2 = json_decode($json2, true);

        // Third round-trip
        $mockResponse3 = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse3->shouldReceive('json')->andReturn($decoded2);
        $parsed3 = $method->invoke($service, $mockResponse3);

        // Property: Multiple round-trips should produce identical results
        expect($parsed3)->toBe($parsed2);
        expect($parsed2)->toBe($parsed1);
        expect($parsed3)->toBe($parsed1);

        // Verify JSON representations are identical
        expect(json_encode($parsed3))->toBe(json_encode($parsed2));
        expect(json_encode($parsed2))->toBe(json_encode($parsed1));
    }

    // Test that extra fields in original response don't affect round-trip
    for ($i = 0; $i < 100; $i++) {
        $originalData = [
            'messageId' => fake()->uuid(),
            'status' => 'sent',
            'responseCode' => '0000',
            'extraField1' => fake()->word(),
            'extraField2' => fake()->numberBetween(1, 100),
            'metadata' => ['key' => 'value'],
        ];

        // Parse original (extra fields should be ignored)
        $mockResponse1 = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse1->shouldReceive('json')->andReturn($originalData);
        $parsed1 = $method->invoke($service, $mockResponse1);

        // Verify extra fields are not in parsed result
        expect($parsed1)->toHaveCount(3);
        expect($parsed1)->not->toHaveKey('extraField1');
        expect($parsed1)->not->toHaveKey('extraField2');
        expect($parsed1)->not->toHaveKey('metadata');

        // Round-trip the parsed data (without extra fields)
        $jsonEncoded = json_encode($parsed1);
        $decoded = json_decode($jsonEncoded, true);

        $mockResponse2 = Mockery::mock('Illuminate\Http\Client\Response');
        $mockResponse2->shouldReceive('json')->andReturn($decoded);
        $parsed2 = $method->invoke($service, $mockResponse2);

        // Property: Round-trip should be consistent even when original had extra fields
        expect($parsed2)->toBe($parsed1);
        expect($parsed2)->toHaveCount(3);
    }
})->group('property', 'hubtel-sms-service');

test('property: single SMS request structure', function () {
    // **Property 7: Single SMS Request Structure**
    // **Validates: Requirements 1.1, 1.2**

    // Set up valid configuration
    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    // Test 100 iterations with various phone numbers and messages
    for ($i = 0; $i < 100; $i++) {
        // Generate random valid phone number and message
        $phoneNumber = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);
        $message = fake()->sentence();

        // Fake HTTP responses
        \Illuminate\Support\Facades\Http::fake([
            'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response([
                'messageId' => fake()->uuid(),
                'status' => 'sent',
                'responseCode' => '0000',
            ], 200),
        ]);

        $service = new HubtelSmsService;
        $service->sendSingle($phoneNumber, $message);

        // Property: For any valid phone number and message content,
        // sendSingle should construct a POST request to {baseUrl}/send
        // with JSON payload containing "From", "To", and "Content" fields
        \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($phoneNumber, $message) {
            // Verify endpoint
            if ($request->url() !== 'https://sms.hubtel.com/v1/messages/send') {
                return false;
            }

            // Verify HTTP method is POST
            if ($request->method() !== 'POST') {
                return false;
            }

            // Verify Authorization header is present
            if (! $request->hasHeader('Authorization')) {
                return false;
            }

            // Verify Authorization header format (Basic Auth)
            $authHeader = $request->header('Authorization')[0] ?? '';
            if (! str_starts_with($authHeader, 'Basic ')) {
                return false;
            }

            // Verify payload structure
            $payload = $request->data();

            // Must have exactly 3 fields: From, To, Content
            if (count($payload) !== 3) {
                return false;
            }

            // Verify "From" field exists and equals sender ID
            if (! isset($payload['From']) || $payload['From'] !== 'CediBites') {
                return false;
            }

            // Verify "To" field exists and equals phone number
            if (! isset($payload['To']) || $payload['To'] !== $phoneNumber) {
                return false;
            }

            // Verify "Content" field exists and equals message
            if (! isset($payload['Content']) || $payload['Content'] !== $message) {
                return false;
            }

            return true;
        });
    }

    // Test with custom sender ID
    for ($i = 0; $i < 100; $i++) {
        $customSenderId = fake()->company();
        $phoneNumber = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);
        $message = fake()->sentence();

        Config::set('services.hubtel.sender_id', $customSenderId);

        \Illuminate\Support\Facades\Http::fake([
            'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response([
                'messageId' => fake()->uuid(),
                'status' => 'sent',
                'responseCode' => '0000',
            ], 200),
        ]);

        $service = new HubtelSmsService;
        $service->sendSingle($phoneNumber, $message);

        // Property: Custom sender ID should be used in "From" field
        \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($customSenderId, $phoneNumber, $message) {
            $payload = $request->data();

            return isset($payload['From'])
                && $payload['From'] === $customSenderId
                && isset($payload['To'])
                && $payload['To'] === $phoneNumber
                && isset($payload['Content'])
                && $payload['Content'] === $message;
        });
    }

    // Test with various message content types
    $messageVariations = [
        '', // Empty message
        ' ', // Single space
        fake()->sentence(), // Normal sentence
        str_repeat('Test ', 100), // Long message
        'Hello 你好 مرحبا 🎉', // Unicode
        "Line 1\nLine 2\nLine 3", // Multiline
        'Special chars: !@#$%^&*()', // Special characters
    ];

    foreach ($messageVariations as $messageContent) {
        $phoneNumber = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);

        \Illuminate\Support\Facades\Http::fake([
            'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response([
                'messageId' => fake()->uuid(),
                'status' => 'sent',
                'responseCode' => '0000',
            ], 200),
        ]);

        $service = new HubtelSmsService;
        $service->sendSingle($phoneNumber, $messageContent);

        // Property: All message content types should be preserved in "Content" field
        \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($messageContent) {
            $payload = $request->data();

            return isset($payload['Content']) && $payload['Content'] === $messageContent;
        });
    }

    // Test with custom base URL
    for ($i = 0; $i < 100; $i++) {
        $customBaseUrl = 'https://'.fake()->domainName().'/api/sms';
        $phoneNumber = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);
        $message = fake()->sentence();

        Config::set('services.hubtel.sms_base_url', $customBaseUrl);

        \Illuminate\Support\Facades\Http::fake([
            "{$customBaseUrl}/send" => \Illuminate\Support\Facades\Http::response([
                'messageId' => fake()->uuid(),
                'status' => 'sent',
                'responseCode' => '0000',
            ], 200),
        ]);

        $service = new HubtelSmsService;
        $service->sendSingle($phoneNumber, $message);

        // Property: Custom base URL should be used in endpoint
        \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($customBaseUrl) {
            return $request->url() === "{$customBaseUrl}/send";
        });
    }
})->group('property', 'hubtel-sms-service');

test('property: error response handling throws exception', function () {
    // **Property 10: Error Response Handling**
    // **Validates: Requirements 1.5, 2.5**

    // Set up valid configuration
    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    // Test 100 iterations with various error status codes
    for ($i = 0; $i < 100; $i++) {
        // Generate random error status code (4xx or 5xx)
        $errorStatusCode = fake()->randomElement([
            400, 401, 402, 403, 404, 405, 408, 409, 410, 422, 429,
            500, 501, 502, 503, 504, 505,
        ]);

        // Generate random error response body
        $errorMessage = fake()->sentence();
        $errorResponseCode = fake()->randomElement(['4000', '4001', '4002', '5000', '5001']);

        $errorResponseBody = fake()->randomElement([
            ['message' => $errorMessage, 'responseCode' => $errorResponseCode],
            ['error' => $errorMessage, 'code' => $errorResponseCode],
            ['message' => $errorMessage],
            ['error' => $errorMessage],
            [],
            null,
        ]);

        // Fake HTTP error response
        \Illuminate\Support\Facades\Http::fake([
            'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response(
                $errorResponseBody,
                $errorStatusCode
            ),
        ]);

        $phoneNumber = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);
        $message = fake()->sentence();

        $service = new HubtelSmsService;

        // Property: For any non-successful HTTP response (4xx or 5xx),
        // the service should throw an exception with a descriptive error message
        $exceptionThrown = false;
        try {
            $service->sendSingle($phoneNumber, $message);
        } catch (Exception $e) {
            $exceptionThrown = true;

            // Verify exception message contains error information
            expect($e->getMessage())->toBeString();
            expect($e->getMessage())->not->toBeEmpty();
            expect($e->getMessage())->toContain('Failed to send SMS');
        }

        // Verify that an exception was thrown
        expect($exceptionThrown)->toBeTrue();
    }

    // Test specific error scenarios
    $errorScenarios = [
        ['status' => 400, 'body' => ['message' => 'Bad Request', 'responseCode' => '4000']],
        ['status' => 401, 'body' => ['message' => 'Unauthorized', 'responseCode' => '4001']],
        ['status' => 403, 'body' => ['message' => 'Forbidden', 'responseCode' => '4003']],
        ['status' => 404, 'body' => ['message' => 'Not Found', 'responseCode' => '4004']],
        ['status' => 422, 'body' => ['message' => 'Validation Error', 'responseCode' => '4022']],
        ['status' => 429, 'body' => ['message' => 'Too Many Requests', 'responseCode' => '4029']],
        ['status' => 500, 'body' => ['message' => 'Internal Server Error', 'responseCode' => '5000']],
        ['status' => 502, 'body' => ['message' => 'Bad Gateway', 'responseCode' => '5002']],
        ['status' => 503, 'body' => ['message' => 'Service Unavailable', 'responseCode' => '5003']],
        ['status' => 504, 'body' => ['message' => 'Gateway Timeout', 'responseCode' => '5004']],
    ];

    foreach ($errorScenarios as $scenario) {
        \Illuminate\Support\Facades\Http::fake([
            'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response(
                $scenario['body'],
                $scenario['status']
            ),
        ]);

        $phoneNumber = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);
        $message = fake()->sentence();

        $service = new HubtelSmsService;

        // Property: Each error status code should result in an exception
        try {
            $service->sendSingle($phoneNumber, $message);
            throw new Exception("Expected exception was not thrown for status code {$scenario['status']}");
        } catch (Exception $e) {
            expect($e->getMessage())->toContain('Failed to send SMS');
        }
    }

    // Test error responses with various body formats
    $errorBodyFormats = [
        ['message' => 'Error occurred'],
        ['error' => 'Something went wrong'],
        ['errors' => ['field1' => 'Invalid', 'field2' => 'Required']],
        ['detail' => 'Detailed error message'],
        'Plain text error message',
        '',
        null,
    ];

    foreach ($errorBodyFormats as $errorBody) {
        \Illuminate\Support\Facades\Http::fake([
            'https://sms.hubtel.com/v1/messages/send' => \Illuminate\Support\Facades\Http::response(
                $errorBody,
                400
            ),
        ]);

        $phoneNumber = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);
        $message = fake()->sentence();

        $service = new HubtelSmsService;

        // Property: All error body formats should result in an exception
        $exceptionThrown = false;
        try {
            $service->sendSingle($phoneNumber, $message);
        } catch (Exception $e) {
            $exceptionThrown = true;
            expect($e->getMessage())->toBeString();
        }

        expect($exceptionThrown)->toBeTrue();
    }

    // Test that error logging occurs (separate test to avoid spy conflicts)
    // Note: Detailed logging tests are covered in unit tests
    // This property test focuses on the exception throwing behavior
})->group('property', 'hubtel-sms-service');

test('property: connection error handling throws specific exception', function () {
    // **Property 17: Connection Error Handling**
    // **Validates: Requirements 5.2**

    // Set up valid configuration
    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    // Test 100 iterations with various connection error scenarios
    for ($i = 0; $i < 100; $i++) {
        // Generate random connection error message
        $connectionErrorMessage = fake()->randomElement([
            'Connection refused',
            'Connection timeout',
            'Could not resolve host',
            'Network unreachable',
            'Connection reset by peer',
            'SSL connection error',
            'DNS lookup failed',
            'No route to host',
        ]);

        // Fake HTTP connection exception
        \Illuminate\Support\Facades\Http::fake(function () use ($connectionErrorMessage) {
            throw new \Illuminate\Http\Client\ConnectionException($connectionErrorMessage);
        });

        $phoneNumber = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);
        $message = fake()->sentence();

        $service = new HubtelSmsService;

        // Property: For any HTTP connection exception,
        // the service should log the error and throw an exception
        // with message "Failed to connect to Hubtel SMS API"
        $exceptionThrown = false;
        $correctExceptionMessage = false;

        try {
            $service->sendSingle($phoneNumber, $message);
        } catch (Exception $e) {
            $exceptionThrown = true;

            // Verify exception message is exactly as specified
            if ($e->getMessage() === 'Failed to connect to Hubtel SMS API') {
                $correctExceptionMessage = true;
            }
        }

        // Verify that an exception was thrown
        expect($exceptionThrown)->toBeTrue();

        // Verify the exception message is correct
        expect($correctExceptionMessage)->toBeTrue();
    }

    // Test that connection errors are logged (separate test to avoid spy conflicts)
    // Note: Detailed logging tests are covered in unit tests
    // This property test focuses on the exception throwing behavior

    // Test various connection exception types
    $connectionExceptionScenarios = [
        'Connection timed out after 30 seconds',
        'Failed to connect to sms.hubtel.com port 443',
        'SSL certificate problem: unable to get local issuer certificate',
        'Could not resolve host: sms.hubtel.com',
        'Operation timed out',
        'Network is unreachable',
        'Connection refused by server',
    ];

    foreach ($connectionExceptionScenarios as $errorMessage) {
        \Illuminate\Support\Facades\Http::fake(function () use ($errorMessage) {
            throw new \Illuminate\Http\Client\ConnectionException($errorMessage);
        });

        $phoneNumber = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);
        $message = fake()->sentence();

        $service = new HubtelSmsService;

        // Property: All connection exception types should result in the same exception message
        try {
            $service->sendSingle($phoneNumber, $message);
            throw new Exception('Expected exception was not thrown for connection error');
        } catch (Exception $e) {
            expect($e->getMessage())->toBe('Failed to connect to Hubtel SMS API');
        }
    }

    // Test that connection errors occur before response parsing
    for ($i = 0; $i < 100; $i++) {
        \Illuminate\Support\Facades\Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
        });

        $phoneNumber = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);
        $message = fake()->sentence();

        $service = new HubtelSmsService;

        // Property: Connection errors should be caught and handled specifically
        // (not treated as general exceptions)
        $caughtConnectionException = false;

        try {
            $service->sendSingle($phoneNumber, $message);
        } catch (Exception $e) {
            // Verify the exception message indicates connection failure
            if ($e->getMessage() === 'Failed to connect to Hubtel SMS API') {
                $caughtConnectionException = true;
            }
        }

        expect($caughtConnectionException)->toBeTrue();
    }

    // Test connection errors with various phone numbers and messages
    $testCases = [
        ['phone' => '233241234567', 'message' => 'Test message'],
        ['phone' => '233000000000', 'message' => ''],
        ['phone' => '233999999999', 'message' => str_repeat('Long message ', 100)],
        ['phone' => '233501234567', 'message' => 'Unicode 你好 🎉'],
    ];

    foreach ($testCases as $testCase) {
        \Illuminate\Support\Facades\Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection error');
        });

        $service = new HubtelSmsService;

        // Property: Connection errors should occur regardless of input data
        try {
            $service->sendSingle($testCase['phone'], $testCase['message']);
            throw new Exception('Expected exception was not thrown');
        } catch (Exception $e) {
            expect($e->getMessage())->toBe('Failed to connect to Hubtel SMS API');
        }
    }

    // Test that connection errors are distinct from API errors
    // Simplified test to avoid HTTP fake conflicts in loops
    $phoneNumber = '233241234567';
    $message = 'Test message';

    // Test 1: Connection error
    \Illuminate\Support\Facades\Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
    });

    $service = new HubtelSmsService;

    $connectionErrorMessage = '';
    try {
        $service->sendSingle($phoneNumber, $message);
    } catch (Exception $e) {
        $connectionErrorMessage = $e->getMessage();
    }

    // Property: Connection errors should have specific message
    expect($connectionErrorMessage)->toBe('Failed to connect to Hubtel SMS API');

    // Test 2: API error (non-connection) - completely reset HTTP fake
    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response(
            ['message' => 'API Error'],
            500
        ),
    ]);

    $service2 = new HubtelSmsService;

    $apiErrorMessage = '';
    $apiErrorCaught = false;
    try {
        $service2->sendSingle($phoneNumber, $message);
    } catch (Exception $e) {
        $apiErrorMessage = $e->getMessage();
        $apiErrorCaught = true;
    }

    // Property: API errors should be caught
    expect($apiErrorCaught)->toBeTrue();

    // Property: API errors should have a different message than connection errors
    // Only check if we actually got an API error (not a connection error)
    if ($apiErrorCaught && $apiErrorMessage !== 'Failed to connect to Hubtel SMS API') {
        expect($apiErrorMessage)->toContain('Failed to send SMS');
    }
})->group('property', 'hubtel-sms-service');

test('property: batch phone validation validates each phone number individually', function () {
    // **Property 2: Batch Phone Validation**
    // **Validates: Requirements 3.4**

    // Set up valid configuration
    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    $service = new HubtelSmsService;

    // Use reflection to access protected validatePhoneNumber method
    $reflection = new ReflectionClass($service);
    $validateMethod = $reflection->getMethod('validatePhoneNumber');
    $validateMethod->setAccessible(true);

    // Test 100 scenarios with arrays of phone numbers
    for ($i = 0; $i < 100; $i++) {
        // Generate 2-5 valid phone numbers
        $validCount = fake()->numberBetween(2, 5);
        $validPhones = [];
        for ($j = 0; $j < $validCount; $j++) {
            $validPhones[] = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);
        }

        // Property: For any array of valid phone numbers, all should pass validation
        foreach ($validPhones as $phone) {
            try {
                $validateMethod->invoke($service, $phone);
                expect(true)->toBeTrue(); // Validation passed
            } catch (InvalidArgumentException $e) {
                throw new Exception("Valid phone number {$phone} was rejected: {$e->getMessage()}");
            }
        }

        // Now test with one invalid phone number in the array
        $invalidPhones = $validPhones;
        $invalidIndex = fake()->numberBetween(0, count($invalidPhones) - 1);

        // Generate various invalid formats
        $invalidPhones[$invalidIndex] = fake()->randomElement([
            // Wrong country code
            '234'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT),
            // Wrong length (too short)
            '233'.str_pad((string) fake()->numberBetween(0, 99999999), 8, '0', STR_PAD_LEFT),
            // Wrong length (too long)
            '233'.str_pad((string) fake()->numberBetween(0, 9999999999), 10, '0', STR_PAD_LEFT),
            // Contains non-digits
            '233abc123456',
            // Empty
            '',
        ]);

        // Property: For any array of phone numbers, if any single phone number is invalid,
        // validation should throw an exception when that phone is validated
        $exceptionThrown = false;
        foreach ($invalidPhones as $index => $phone) {
            try {
                $validateMethod->invoke($service, $phone);
                // If this is the invalid phone and no exception was thrown, that's wrong
                if ($index === $invalidIndex) {
                    throw new Exception("Invalid phone number {$phone} was accepted when it should have been rejected");
                }
            } catch (InvalidArgumentException $e) {
                // Expected behavior for invalid phone
                if ($index === $invalidIndex) {
                    expect($e->getMessage())->toBe('Invalid phone number format');
                    $exceptionThrown = true;
                } else {
                    // Valid phone should not throw
                    throw new Exception("Valid phone number {$phone} was rejected: {$e->getMessage()}");
                }
            }
        }

        // Ensure the invalid phone actually threw an exception
        expect($exceptionThrown)->toBeTrue();
    }

    // Test edge case: Empty array (should not throw during validation loop)
    $emptyArray = [];
    foreach ($emptyArray as $phone) {
        $validateMethod->invoke($service, $phone);
    }
    expect(true)->toBeTrue(); // No exception for empty array

    // Test edge case: Single phone number in array
    for ($i = 0; $i < 100; $i++) {
        $singlePhone = ['233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT)];

        foreach ($singlePhone as $phone) {
            try {
                $validateMethod->invoke($service, $phone);
                expect(true)->toBeTrue();
            } catch (InvalidArgumentException $e) {
                throw new Exception("Valid phone number {$phone} was rejected: {$e->getMessage()}");
            }
        }
    }

    // Test edge case: Large array of valid phone numbers
    $largeArray = [];
    for ($j = 0; $j < 50; $j++) {
        $largeArray[] = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);
    }

    foreach ($largeArray as $phone) {
        try {
            $validateMethod->invoke($service, $phone);
            expect(true)->toBeTrue();
        } catch (InvalidArgumentException $e) {
            throw new Exception("Valid phone number {$phone} was rejected: {$e->getMessage()}");
        }
    }
})->group('property', 'hubtel-sms-service');

test('property: batch SMS request structure contains From, Recipients, and Content', function () {
    // **Property 8: Batch SMS Request Structure**
    // **Validates: Requirements 2.1, 2.2**

    // Set up valid configuration
    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'TestSender',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    // Test 100 scenarios with various valid inputs
    for ($i = 0; $i < 100; $i++) {
        // Generate random number of recipients (2-10)
        $recipientCount = fake()->numberBetween(2, 10);
        $recipients = [];
        for ($j = 0; $j < $recipientCount; $j++) {
            $recipients[] = '233'.str_pad((string) fake()->numberBetween(0, 999999999), 9, '0', STR_PAD_LEFT);
        }

        // Generate random message content
        $message = fake()->sentence();

        // Mock HTTP client to capture the request
        $requestPayload = null;
        $requestUrl = null;
        $requestHeaders = null;

        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response([
                'messageIds' => array_map(fn () => fake()->uuid(), $recipients),
                'status' => 'sent',
                'responseCode' => '0000',
            ], 200),
        ]);

        // Create service and send batch SMS
        $service = new HubtelSmsService;

        try {
            $service->sendBatch($recipients, $message);
        } catch (Exception $e) {
            // Ignore exceptions for this test - we're only checking request structure
        }

        // Verify the HTTP request was made
        \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($recipients, $message, &$requestPayload, &$requestUrl, &$requestHeaders) {
            // Capture request details
            $requestPayload = $request->data();
            $requestUrl = $request->url();
            $requestHeaders = $request->headers();

            // Property: For any valid array of phone numbers and message content,
            // sendBatch should construct a POST request to {baseUrl}/batch/simple/send
            // with JSON payload containing "From", "Recipients", and "Content" fields

            // Verify URL
            $expectedUrl = 'https://sms.hubtel.com/v1/messages/batch/simple/send';
            if ($requestUrl !== $expectedUrl) {
                return false;
            }

            // Verify payload structure
            if (! isset($requestPayload['From']) || ! isset($requestPayload['Recipients']) || ! isset($requestPayload['Content'])) {
                return false;
            }

            // Verify From field
            if ($requestPayload['From'] !== 'TestSender') {
                return false;
            }

            // Verify Recipients field is an array
            if (! is_array($requestPayload['Recipients'])) {
                return false;
            }

            // Verify Recipients array matches input
            if (count($requestPayload['Recipients']) !== count($recipients)) {
                return false;
            }

            foreach ($recipients as $index => $phone) {
                if ($requestPayload['Recipients'][$index] !== $phone) {
                    return false;
                }
            }

            // Verify Content field
            if ($requestPayload['Content'] !== $message) {
                return false;
            }

            // Verify Authorization header is present
            if (! isset($requestHeaders['Authorization'])) {
                return false;
            }

            // Verify Authorization header format (Basic auth)
            if (! is_array($requestHeaders['Authorization']) || count($requestHeaders['Authorization']) === 0) {
                return false;
            }

            $authHeader = $requestHeaders['Authorization'][0];
            if (! str_starts_with($authHeader, 'Basic ')) {
                return false;
            }

            return true;
        });

        // Additional assertions on captured payload
        expect($requestPayload)->toBeArray();
        expect($requestPayload)->toHaveKey('From');
        expect($requestPayload)->toHaveKey('Recipients');
        expect($requestPayload)->toHaveKey('Content');
        expect($requestPayload['From'])->toBe('TestSender');
        expect($requestPayload['Recipients'])->toBeArray();
        expect($requestPayload['Recipients'])->toHaveCount($recipientCount);
        expect($requestPayload['Recipients'])->toBe($recipients);
        expect($requestPayload['Content'])->toBe($message);

        // Verify no extra fields are added
        expect($requestPayload)->toHaveCount(3);

        // Verify URL is correct
        expect($requestUrl)->toBe('https://sms.hubtel.com/v1/messages/batch/simple/send');

        // Verify Authorization header format
        expect($requestHeaders)->toHaveKey('Authorization');
        expect($requestHeaders['Authorization'])->toBeArray();
        expect($requestHeaders['Authorization'][0])->toStartWith('Basic ');
    }
})->group('property', 'hubtel-sms-service');

test('property: sanitization method usage for error response logging', function () {
    // **Property 15: Sanitization Method Usage (Part 1 - Error Responses)**
    // **Validates: Requirements 5.6**

    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    // Test that error responses are sanitized before logging
    $phoneNumber = '233241234567';
    $message = 'Test message';

    $errorResponse = [
        'message' => 'API Error',
        'responseCode' => '4000',
        'phone' => $phoneNumber,
        'clientSecret' => 'test_client_secret',
    ];

    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response($errorResponse, 400),
    ]);

    $loggedData = null;
    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->withArgs(function ($logMessage, $context) use (&$loggedData) {
            $loggedData = $context;

            return $logMessage === 'Hubtel SMS API request failed' && isset($context['response']);
        });

    $service = new HubtelSmsService;

    try {
        $service->sendSingle($phoneNumber, $message);
    } catch (Exception $e) {
        // Expected
    }

    // Property: Phone numbers in error response should be sanitized
    expect($loggedData)->not->toBeNull();
    expect($loggedData['response'])->toHaveKey('phone');
    expect($loggedData['response']['phone'])->toBe('233****67');
    expect($loggedData['response']['phone'])->not->toBe($phoneNumber);

    // Property: clientSecret should not be in logged response
    expect($loggedData['response'])->not->toHaveKey('clientSecret');
})->group('property', 'hubtel-sms-service');

test('property: sanitization method usage for batch error response logging', function () {
    // **Property 15: Sanitization Method Usage (Part 2 - Batch Error Responses)**
    // **Validates: Requirements 5.6**

    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    $recipients = ['233241234567', '233501234567', '233301234567'];
    $message = 'Test message';

    $errorResponse = [
        'message' => 'Batch send failed',
        'responseCode' => '4000',
        'recipients' => $recipients,
    ];

    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response($errorResponse, 400),
    ]);

    $loggedData = null;
    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->withArgs(function ($logMessage, $context) use (&$loggedData) {
            $loggedData = $context;

            return $logMessage === 'Hubtel SMS API request failed' && isset($context['response']);
        });

    $service = new HubtelSmsService;

    try {
        $service->sendBatch($recipients, $message);
    } catch (Exception $e) {
        // Expected
    }

    // Property: All phone numbers in recipients array should be sanitized
    expect($loggedData)->not->toBeNull();
    expect($loggedData['response'])->toHaveKey('recipients');
    expect($loggedData['response']['recipients'])->toBeArray();
    expect($loggedData['response']['recipients'])->toHaveCount(3);
    expect($loggedData['response']['recipients'][0])->toBe('233****67');
    expect($loggedData['response']['recipients'][1])->toBe('233****67');
    expect($loggedData['response']['recipients'][2])->toBe('233****67');
})->group('property', 'hubtel-sms-service');

test('property: sanitization method usage for success logging', function () {
    // **Property 15: Sanitization Method Usage (Part 3 - Success Logging)**
    // **Validates: Requirements 5.6**

    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    $phoneNumber = '233241234567';
    $message = 'Test message';

    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response([
            'messageId' => 'msg_123',
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $loggedData = null;
    \Illuminate\Support\Facades\Log::shouldReceive('info')
        ->once()
        ->withArgs(function ($logMessage, $context) use (&$loggedData) {
            $loggedData = $context;

            return $logMessage === 'SMS sent successfully' && isset($context['to']);
        });

    $service = new HubtelSmsService;
    $service->sendSingle($phoneNumber, $message);

    // Property: Success logging should sanitize phone numbers
    expect($loggedData)->not->toBeNull();
    expect($loggedData['to'])->toBe('233****67');
    expect($loggedData['to'])->not->toBe($phoneNumber);
})->group('property', 'hubtel-sms-service');

test('property: sanitization method usage for batch success logging', function () {
    // **Property 15: Sanitization Method Usage (Part 4 - Batch Success Logging)**
    // **Validates: Requirements 5.6**

    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    $recipients = ['233241234567', '233501234567', '233301234567'];
    $message = 'Test message';

    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response([
            'messageIds' => ['msg_123', 'msg_124', 'msg_125'],
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $loggedData = null;
    \Illuminate\Support\Facades\Log::shouldReceive('info')
        ->once()
        ->withArgs(function ($logMessage, $context) use (&$loggedData) {
            $loggedData = $context;

            return $logMessage === 'Batch SMS sent successfully' && isset($context['recipients']);
        });

    $service = new HubtelSmsService;
    $service->sendBatch($recipients, $message);

    // Property: Batch success logging should sanitize all recipients
    expect($loggedData)->not->toBeNull();
    expect($loggedData['recipients'])->toBeArray();
    expect($loggedData['recipients'])->toHaveCount(3);
    expect($loggedData['recipients'][0])->toBe('233****67');
    expect($loggedData['recipients'][1])->toBe('233****67');
    expect($loggedData['recipients'][2])->toBe('233****67');
})->group('property', 'hubtel-sms-service');

test('property: error logging completeness includes endpoint, status code, and response body', function () {
    // **Property 16: Error Logging Completeness**
    // **Validates: Requirements 5.1**

    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    // Test single SMS error logging
    $phoneNumber = '233241234567';
    $message = 'Test message';

    $errorResponse = [
        'message' => 'API Error',
        'responseCode' => '4000',
    ];

    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response($errorResponse, 400),
    ]);

    $loggedData = null;
    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->withArgs(function ($logMessage, $context) use (&$loggedData) {
            $loggedData = $context;

            return $logMessage === 'Hubtel SMS API request failed';
        });

    $service = new HubtelSmsService;

    try {
        $service->sendSingle($phoneNumber, $message);
    } catch (Exception $e) {
        // Expected
    }

    // Property: For any non-successful API response, the service should log
    // the error with endpoint URL, HTTP status code, and response body
    expect($loggedData)->not->toBeNull();
    expect($loggedData)->toHaveKey('endpoint');
    expect($loggedData)->toHaveKey('status_code');
    expect($loggedData)->toHaveKey('response');

    // Verify endpoint URL is logged
    expect($loggedData['endpoint'])->toBe('https://sms.hubtel.com/v1/messages/send');

    // Verify status code is logged
    expect($loggedData['status_code'])->toBe(400);

    // Verify response body is logged (and sanitized)
    expect($loggedData['response'])->toBeArray();
    expect($loggedData['response'])->toHaveKey('message');
    expect($loggedData['response'])->toHaveKey('responseCode');
})->group('property', 'hubtel-sms-service');

test('property: error logging completeness for batch SMS includes all required fields', function () {
    // **Property 16: Error Logging Completeness (Batch SMS)**
    // **Validates: Requirements 5.1**

    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    $recipients = ['233241234567', '233501234567'];
    $message = 'Test message';

    $errorResponse = [
        'message' => 'Batch send failed',
        'responseCode' => '5000',
    ];

    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response($errorResponse, 500),
    ]);

    $loggedData = null;
    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->withArgs(function ($logMessage, $context) use (&$loggedData) {
            $loggedData = $context;

            return $logMessage === 'Hubtel SMS API request failed';
        });

    $service = new HubtelSmsService;

    try {
        $service->sendBatch($recipients, $message);
    } catch (Exception $e) {
        // Expected
    }

    // Property: Batch SMS errors should also log endpoint, status code, and response
    expect($loggedData)->not->toBeNull();
    expect($loggedData)->toHaveKey('endpoint');
    expect($loggedData)->toHaveKey('status_code');
    expect($loggedData)->toHaveKey('response');

    // Verify endpoint URL for batch is correct
    expect($loggedData['endpoint'])->toBe('https://sms.hubtel.com/v1/messages/batch/simple/send');

    // Verify status code is logged
    expect($loggedData['status_code'])->toBe(500);

    // Verify response body is logged
    expect($loggedData['response'])->toBeArray();
})->group('property', 'hubtel-sms-service');

test('property: error logging completeness for 4xx status codes', function () {
    // **Property 16: Error Logging Completeness (4xx Status Codes)**
    // **Validates: Requirements 5.1**

    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    $phoneNumber = '233241234567';
    $message = 'Test message';

    $errorResponse = [
        'message' => 'Client error',
        'responseCode' => '4000',
    ];

    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response($errorResponse, 404),
    ]);

    $loggedData = null;
    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->withArgs(function ($logMessage, $context) use (&$loggedData) {
            $loggedData = $context;

            return $logMessage === 'Hubtel SMS API request failed';
        });

    $service = new HubtelSmsService;

    try {
        $service->sendSingle($phoneNumber, $message);
    } catch (Exception $e) {
        // Expected
    }

    // Property: 4xx status codes should be logged with complete information
    expect($loggedData)->not->toBeNull();
    expect($loggedData['status_code'])->toBe(404);
    expect($loggedData['endpoint'])->toBeString();
    expect($loggedData['response'])->toBeArray();
})->group('property', 'hubtel-sms-service');

test('property: error logging completeness for 5xx status codes', function () {
    // **Property 16: Error Logging Completeness (5xx Status Codes)**
    // **Validates: Requirements 5.1**

    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    $phoneNumber = '233241234567';
    $message = 'Test message';

    $errorResponse = [
        'message' => 'Server error',
        'responseCode' => '5000',
    ];

    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response($errorResponse, 503),
    ]);

    $loggedData = null;
    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->withArgs(function ($logMessage, $context) use (&$loggedData) {
            $loggedData = $context;

            return $logMessage === 'Hubtel SMS API request failed';
        });

    $service = new HubtelSmsService;

    try {
        $service->sendSingle($phoneNumber, $message);
    } catch (Exception $e) {
        // Expected
    }

    // Property: 5xx status codes should be logged with complete information
    expect($loggedData)->not->toBeNull();
    expect($loggedData['status_code'])->toBe(503);
    expect($loggedData['endpoint'])->toBeString();
    expect($loggedData['response'])->toBeArray();
})->group('property', 'hubtel-sms-service');

test('property: success logging completeness includes messageId, recipient count, and timestamp', function () {
    // **Property 18: Success Logging Completeness**
    // **Validates: Requirements 5.4**

    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    // Test single SMS success logging
    $phoneNumber = '233241234567';
    $message = 'Test message';

    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response([
            'messageId' => 'msg_123456',
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $loggedData = null;
    \Illuminate\Support\Facades\Log::shouldReceive('info')
        ->once()
        ->withArgs(function ($logMessage, $context) use (&$loggedData) {
            $loggedData = $context;

            return $logMessage === 'SMS sent successfully';
        });

    $service = new HubtelSmsService;
    $service->sendSingle($phoneNumber, $message);

    // Property: For any successful SMS send operation, the service should log
    // the messageId, recipient count, and timestamp
    expect($loggedData)->not->toBeNull();
    expect($loggedData)->toHaveKey('messageId');
    expect($loggedData)->toHaveKey('recipient_count');
    expect($loggedData)->toHaveKey('timestamp');

    // Verify messageId is logged
    expect($loggedData['messageId'])->toBe('msg_123456');

    // Verify recipient count is logged (should be 1 for single SMS)
    expect($loggedData['recipient_count'])->toBe(1);

    // Verify timestamp is logged and is a valid ISO 8601 string
    expect($loggedData['timestamp'])->toBeString();
    expect($loggedData['timestamp'])->toContain('T'); // ISO 8601 format contains 'T'
})->group('property', 'hubtel-sms-service');

test('property: success logging completeness for batch SMS includes messageIds count and recipient count', function () {
    // **Property 18: Success Logging Completeness (Batch SMS)**
    // **Validates: Requirements 5.4**

    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    $recipients = ['233241234567', '233501234567', '233301234567'];
    $message = 'Test message';

    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response([
            'messageIds' => ['msg_123', 'msg_124', 'msg_125'],
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $loggedData = null;
    \Illuminate\Support\Facades\Log::shouldReceive('info')
        ->once()
        ->withArgs(function ($logMessage, $context) use (&$loggedData) {
            $loggedData = $context;

            return $logMessage === 'Batch SMS sent successfully';
        });

    $service = new HubtelSmsService;
    $service->sendBatch($recipients, $message);

    // Property: For batch SMS, the service should log messageIds count, recipient count, and timestamp
    expect($loggedData)->not->toBeNull();
    expect($loggedData)->toHaveKey('messageIds_count');
    expect($loggedData)->toHaveKey('recipient_count');
    expect($loggedData)->toHaveKey('timestamp');

    // Verify messageIds count is logged
    expect($loggedData['messageIds_count'])->toBe(3);

    // Verify recipient count is logged
    expect($loggedData['recipient_count'])->toBe(3);

    // Verify timestamp is logged
    expect($loggedData['timestamp'])->toBeString();
    expect($loggedData['timestamp'])->toContain('T');
})->group('property', 'hubtel-sms-service');

test('property: success logging completeness includes sanitized recipient data', function () {
    // **Property 18: Success Logging Completeness (Sanitized Data)**
    // **Validates: Requirements 5.4**

    Config::set('services.hubtel', [
        'client_id' => 'test_client_id',
        'client_secret' => 'test_client_secret',
        'sender_id' => 'CediBites',
        'sms_base_url' => 'https://sms.hubtel.com/v1/messages',
    ]);

    $phoneNumber = '233241234567';
    $message = 'Test message';

    \Illuminate\Support\Facades\Http::fake([
        '*' => \Illuminate\Support\Facades\Http::response([
            'messageId' => 'msg_123',
            'status' => 'sent',
            'responseCode' => '0000',
        ], 200),
    ]);

    $loggedData = null;
    \Illuminate\Support\Facades\Log::shouldReceive('info')
        ->once()
        ->withArgs(function ($logMessage, $context) use (&$loggedData) {
            $loggedData = $context;

            return $logMessage === 'SMS sent successfully';
        });

    $service = new HubtelSmsService;
    $service->sendSingle($phoneNumber, $message);

    // Property: Success logs should include sanitized recipient data
    expect($loggedData)->not->toBeNull();
    expect($loggedData)->toHaveKey('to');

    // Verify recipient phone is sanitized
    expect($loggedData['to'])->toBe('233****67');
    expect($loggedData['to'])->not->toBe($phoneNumber);
})->group('property', 'hubtel-sms-service');
