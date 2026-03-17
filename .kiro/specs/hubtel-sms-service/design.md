# HubtelSmsService Design Document

## Overview

The HubtelSmsService is a Laravel service class that provides SMS messaging capabilities through the Hubtel SMS API. It follows the same architectural patterns established by the existing HubtelService for payments, ensuring consistency across Hubtel integrations.

The service supports two primary operations:
- **Single SMS**: Send one message to one recipient
- **Batch SMS**: Send the same message to multiple recipients

Key design principles:
- Mirror the HubtelService pattern for consistency
- Simple, focused implementation without complex features
- Configuration through Laravel's config system
- Basic error handling with descriptive exceptions
- Logging with data sanitization for privacy

## Architecture

### Service Layer Pattern

The HubtelSmsService follows Laravel's service layer pattern, encapsulating all Hubtel SMS API interactions in a single, testable class. This approach:
- Keeps controllers thin and focused on HTTP concerns
- Centralizes API logic for reusability
- Simplifies testing through dependency injection
- Maintains consistency with existing HubtelService

### Configuration Management

Configuration is managed through Laravel's `config/services.php` file under the `hubtel` key, sharing credentials with the existing HubtelService:

```php
'hubtel' => [
    'client_id' => env('HUBTEL_CLIENT_ID'),
    'client_secret' => env('HUBTEL_CLIENT_SECRET'),
    'sender_id' => env('HUBTEL_SENDER_ID', 'CediBites'),
    'sms_base_url' => env('HUBTEL_SMS_BASE_URL', 'https://sms.hubtel.com/v1/messages'),
    // ... existing payment config
]
```

### HTTP Client

The service uses Laravel's HTTP facade (`Illuminate\Support\Facades\Http`) for API communication, providing:
- Fluent interface for building requests
- Built-in timeout and retry capabilities
- Easy testing through HTTP fake
- Consistent error handling

## Components and Interfaces

### HubtelSmsService Class

**Location**: `app/Services/HubtelSmsService.php`

**Constructor Dependencies**: None (uses config facade)

**Protected Properties**:
```php
protected ?string $clientId;
protected ?string $clientSecret;
protected ?string $senderId;
protected string $baseUrl;
```

**Public Methods**:

#### sendSingle(string $to, string $message): array

Sends a single SMS message to one recipient.

**Parameters**:
- `$to` (string): Recipient phone number in format 233XXXXXXXXX
- `$message` (string): SMS message content

**Returns**: Array with structure:
```php
[
    'messageId' => string,  // Unique message identifier from Hubtel
    'status' => string,     // Status from Hubtel response
    'responseCode' => string // Response code from Hubtel
]
```

**Throws**:
- `RuntimeException`: When configuration is invalid
- `InvalidArgumentException`: When phone number format is invalid
- `Exception`: When API request fails

**Example Usage**:
```php
$smsService = new HubtelSmsService();
$result = $smsService->sendSingle('233241234567', 'Your OTP is 123456');
// Returns: ['messageId' => 'msg_123', 'status' => 'sent', 'responseCode' => '0000']
```

#### sendBatch(array $recipients, string $message): array

Sends the same SMS message to multiple recipients.

**Parameters**:
- `$recipients` (array): Array of phone numbers in format 233XXXXXXXXX
- `$message` (string): SMS message content

**Returns**: Array with structure:
```php
[
    'messageIds' => array,  // Array of message IDs, one per recipient
    'status' => string,     // Overall status from Hubtel
    'responseCode' => string // Response code from Hubtel
]
```

**Throws**:
- `RuntimeException`: When configuration is invalid
- `InvalidArgumentException`: When any phone number format is invalid
- `Exception`: When API request fails

**Example Usage**:
```php
$smsService = new HubtelSmsService();
$result = $smsService->sendBatch(
    ['233241234567', '233501234567'],
    'Special offer: 20% off today!'
);
// Returns: ['messageIds' => ['msg_123', 'msg_124'], 'status' => 'sent', 'responseCode' => '0000']
```

### Protected Helper Methods

Following the HubtelService pattern, these protected methods provide reusable functionality:

#### validateConfiguration(): void

Validates that required configuration values are present. Throws `RuntimeException` if clientId or clientSecret is missing.

#### getAuthHeader(): string

Builds the Basic Authentication header value by base64 encoding `clientId:clientSecret`.

Returns: `"Basic {base64_encoded_credentials}"`

#### validatePhoneNumber(string $phone): void

Validates that a phone number matches the Ghana format (233XXXXXXXXX). Throws `InvalidArgumentException` if invalid.

Validation rules:
- Must be exactly 12 characters
- Must start with "233"
- Must contain only digits

#### sanitizeForLogging(array $data): array

Sanitizes sensitive data for logging by:
- Masking phone numbers (show first 3 and last 2 digits: `233****67`)
- Removing clientSecret from any data structure
- Preserving all other data for debugging

#### parseResponse(Response $response): array

Parses Hubtel API JSON response and extracts required fields. Throws `Exception` if response format is invalid or missing required fields.

Expected response structure:
```json
{
    "messageId": "msg_123",
    "status": "sent",
    "responseCode": "0000"
}
```

For batch responses:
```json
{
    "messageIds": ["msg_123", "msg_124"],
    "status": "sent",
    "responseCode": "0000"
}
```

## Data Models

### Request Payloads

#### Single SMS Request

**Endpoint**: `POST {baseUrl}/send`

**Payload**:
```json
{
    "From": "CediBites",
    "To": "233241234567",
    "Content": "Your message here"
}
```

#### Batch SMS Request

**Endpoint**: `POST {baseUrl}/batch/simple/send`

**Payload**:
```json
{
    "From": "CediBites",
    "Recipients": ["233241234567", "233501234567"],
    "Content": "Your message here"
}
```

### Response Formats

#### Successful Single SMS Response

**HTTP Status**: 200

**Body**:
```json
{
    "messageId": "msg_abc123",
    "status": "sent",
    "responseCode": "0000"
}
```

#### Successful Batch SMS Response

**HTTP Status**: 200

**Body**:
```json
{
    "messageIds": ["msg_abc123", "msg_abc124"],
    "status": "sent",
    "responseCode": "0000"
}
```

#### Error Response

**HTTP Status**: 4xx or 5xx

**Body**:
```json
{
    "message": "Error description",
    "responseCode": "4000"
}
```

### Phone Number Format

Ghana phone numbers follow the E.164 format without the + prefix:
- **Format**: `233XXXXXXXXX`
- **Length**: 12 digits
- **Country Code**: 233 (Ghana)
- **Example**: `233241234567`

The service validates this format before making API calls to prevent unnecessary API errors.

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Phone Number Validation

*For any* string input, the validatePhoneNumber method should accept it if and only if it starts with "233" and has exactly 12 digits.

**Validates: Requirements 3.1, 3.2, 3.3**

### Property 2: Batch Phone Validation

*For any* array of phone numbers, sendBatch should validate each phone number individually, and should throw an exception if any single phone number is invalid.

**Validates: Requirements 3.4**

### Property 3: Phone Number String Preservation

*For any* valid phone number string with leading zeros, the service should preserve it as a string throughout all operations (validation, logging, API requests).

**Validates: Requirements 3.5**

### Property 4: Basic Authentication Header Format

*For any* configured clientId and clientSecret, the getAuthHeader method should return a string in the format "Basic {base64(clientId:clientSecret)}".

**Validates: Requirements 1.3, 2.3**

### Property 5: Configuration Validation Before API Calls

*For any* API operation (sendSingle or sendBatch), if clientId or clientSecret is empty, the service should throw a RuntimeException before making any HTTP request.

**Validates: Requirements 4.5, 4.6**

### Property 6: Configuration Defaults

*For any* missing optional configuration value (senderId or sms_base_url), the service should use the documented default value ("CediBites" and "https://sms.hubtel.com/v1/messages" respectively).

**Validates: Requirements 4.3, 4.4**

### Property 7: Single SMS Request Structure

*For any* valid phone number and message content, sendSingle should construct a POST request to {baseUrl}/send with JSON payload containing "From", "To", and "Content" fields.

**Validates: Requirements 1.1, 1.2**

### Property 8: Batch SMS Request Structure

*For any* valid array of phone numbers and message content, sendBatch should construct a POST request to {baseUrl}/batch/simple/send with JSON payload containing "From", "Recipients", and "Content" fields.

**Validates: Requirements 2.1, 2.2**

### Property 9: Successful Response Parsing

*For any* successful API response (HTTP 200), the service should extract and return messageId (or messageIds for batch), status, and responseCode from the JSON response body.

**Validates: Requirements 1.4, 2.4, 6.2, 6.3, 6.4**

### Property 10: Error Response Handling

*For any* non-successful HTTP response (4xx or 5xx), the service should throw an exception with a descriptive error message.

**Validates: Requirements 1.5, 2.5**

### Property 11: Invalid Response Format Handling

*For any* response with invalid JSON or missing required fields (messageId, status, responseCode), the parseResponse method should throw an exception with message "Invalid API response format".

**Validates: Requirements 6.5**

### Property 12: Response Round-Trip

*For any* valid Hubtel SMS API response, parsing the response then encoding it back to JSON then parsing again should produce an equivalent data structure.

**Validates: Requirements 6.6**

### Property 13: Phone Number Sanitization in Logs

*For any* phone number logged by the service, the log entry should show only the first 3 and last 2 digits, with middle digits masked (e.g., "233****67").

**Validates: Requirements 1.6, 5.3**

### Property 14: Client Secret Never Logged

*For any* log entry produced by the service, it should never contain the clientSecret value in plain text.

**Validates: Requirements 5.5**

### Property 15: Sanitization Method Usage

*For any* request payload logged by the service, the sanitizeForLogging method should be called to mask sensitive data before logging.

**Validates: Requirements 5.6**

### Property 16: Error Logging Completeness

*For any* non-successful API response, the service should log the error with endpoint URL, HTTP status code, and response body.

**Validates: Requirements 5.1**

### Property 17: Connection Error Handling

*For any* HTTP connection exception, the service should log the error and throw an exception with message "Failed to connect to Hubtel SMS API".

**Validates: Requirements 5.2**

### Property 18: Success Logging Completeness

*For any* successful SMS send operation, the service should log the messageId (or count of messageIds for batch), recipient count, and timestamp.

**Validates: Requirements 5.4**

## Error Handling

### Error Categories

The service handles three categories of errors:

1. **Configuration Errors**: Missing or invalid credentials
   - Detected before API calls
   - Throws `RuntimeException`
   - Example: "Hubtel SMS is not properly configured"

2. **Validation Errors**: Invalid phone numbers or input data
   - Detected before API calls
   - Throws `InvalidArgumentException`
   - Example: "Invalid phone number format"

3. **API Errors**: HTTP failures or invalid responses
   - Detected during/after API calls
   - Throws `Exception`
   - Examples: "Failed to connect to Hubtel SMS API", "Invalid API response format"

### Error Flow

```
Request → Validate Config → Validate Input → Make API Call → Parse Response
            ↓                    ↓                ↓               ↓
      RuntimeException   InvalidArgument    Exception       Exception
```

### Logging Strategy

All errors are logged with appropriate context:

**Configuration Errors**:
```php
Log::error('Hubtel SMS configuration invalid', [
    'missing_fields' => ['client_id', 'client_secret']
]);
```

**Validation Errors**:
```php
Log::warning('Invalid phone number for SMS', [
    'phone' => '233****67', // Sanitized
    'error' => 'Invalid phone number format'
]);
```

**API Errors**:
```php
Log::error('Hubtel SMS API request failed', [
    'endpoint' => 'https://sms.hubtel.com/v1/messages/send',
    'status_code' => 400,
    'response' => $sanitizedResponse
]);
```

**Connection Errors**:
```php
Log::error('Failed to connect to Hubtel SMS API', [
    'endpoint' => 'https://sms.hubtel.com/v1/messages/send',
    'error' => $exception->getMessage()
]);
```

### No Retry Logic

Unlike the HubtelService payment implementation, the SMS service does NOT implement automatic retry logic. Rationale:
- SMS delivery is typically fast and reliable
- Failed SMS can be retried at the application level if needed
- Simpler implementation reduces complexity
- Avoids potential duplicate message sends

If retry logic is needed in the future, it can be added following the HubtelService pattern.

## Testing Strategy

### Dual Testing Approach

The HubtelSmsService will be tested using both unit tests and property-based tests:

**Unit Tests** focus on:
- Specific examples of valid SMS sends
- Edge cases (empty messages, boundary phone numbers)
- Error conditions (missing config, invalid responses)
- Integration points (HTTP client, logging)

**Property-Based Tests** focus on:
- Universal properties that hold for all inputs
- Phone number validation across many formats
- Data sanitization across various inputs
- Response parsing across different valid structures

Together, these approaches provide comprehensive coverage: unit tests catch concrete bugs, property tests verify general correctness.

### Property-Based Testing Configuration

**Framework**: Pest with Pest Property Plugin

**Configuration**:
- Minimum 100 iterations per property test
- Each test references its design document property
- Tag format: `Feature: hubtel-sms-service, Property {number}: {property_text}`

**Example Property Test**:
```php
test('phone number validation accepts valid format', function ($phone) {
    $service = new HubtelSmsService();
    
    // Should not throw exception for valid phone
    $service->validatePhoneNumber($phone);
    
    expect(true)->toBeTrue();
})->with(function () {
    // Generate 100 valid Ghana phone numbers
    for ($i = 0; $i < 100; $i++) {
        yield '233' . str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT);
    }
});
// Feature: hubtel-sms-service, Property 1: Phone Number Validation
```

### Test Coverage Goals

- **Line Coverage**: Minimum 90%
- **Branch Coverage**: Minimum 85%
- **Property Tests**: All 18 correctness properties implemented
- **Unit Tests**: All edge cases and error conditions covered

### Testing Tools

- **Pest 4**: Primary testing framework
- **HTTP Fake**: Mock Hubtel API responses
- **Log Fake**: Verify logging behavior
- **Config Fake**: Test configuration scenarios

### Test Organization

```
tests/
├── Unit/
│   └── Services/
│       ├── HubtelSmsServiceTest.php          # Unit tests
│       └── HubtelSmsServicePropertyTest.php  # Property-based tests
└── Feature/
    └── Api/
        └── HubtelSmsIntegrationTest.php      # Integration tests (if needed)
```
