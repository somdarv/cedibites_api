# Implementation Plan: HubtelSmsService

## Overview

This plan implements the HubtelSmsService to replace the existing SMSService with a focused Hubtel SMS API integration. The implementation follows Laravel 12 conventions and mirrors the patterns established by the existing HubtelService for payments.

## Tasks

- [x] 1. Update configuration and create HubtelSmsService class
  - [x] 1.1 Add SMS configuration to config/services.php
    - Add `sms_base_url` to the hubtel config array with default "https://sms.hubtel.com/v1/messages"
    - Keep existing hubtel config (client_id, client_secret, sender_id)
    - _Requirements: 4.1, 4.2, 4.3, 4.4_
  
  - [x] 1.2 Create HubtelSmsService class with constructor and properties
    - Create `app/Services/HubtelSmsService.php`
    - Add protected properties: clientId, clientSecret, senderId, baseUrl
    - Implement constructor that loads config values
    - _Requirements: 4.1, 4.2, 4.3, 4.4_
  
  - [x] 1.3 Write property test for configuration defaults
    - **Property 6: Configuration Defaults**
    - **Validates: Requirements 4.3, 4.4**

- [x] 2. Implement configuration validation and authentication
  - [x] 2.1 Implement validateConfiguration method
    - Check clientId and clientSecret are not empty
    - Throw RuntimeException with message "Hubtel SMS is not properly configured" if invalid
    - _Requirements: 4.5, 4.6_
  
  - [x] 2.2 Implement getAuthHeader method
    - Build Basic Auth header by base64 encoding clientId:clientSecret
    - Return format: "Basic {base64_encoded_credentials}"
    - _Requirements: 1.3, 2.3_
  
  - [x] 2.3 Write property test for Basic Auth header format
    - **Property 4: Basic Authentication Header Format**
    - **Validates: Requirements 1.3, 2.3**
  
  - [x] 2.4 Write property test for configuration validation
    - **Property 5: Configuration Validation Before API Calls**
    - **Validates: Requirements 4.5, 4.6**

- [x] 3. Implement phone number validation
  - [x] 3.1 Implement validatePhoneNumber method
    - Check phone starts with "233" and has exactly 12 digits
    - Throw InvalidArgumentException with message "Invalid phone number format" if invalid
    - _Requirements: 3.1, 3.2, 3.3, 3.5_
  
  - [x] 3.2 Write property test for phone number validation
    - **Property 1: Phone Number Validation**
    - **Validates: Requirements 3.1, 3.2, 3.3**
  
  - [x] 3.3 Write property test for phone number string preservation
    - **Property 3: Phone Number String Preservation**
    - **Validates: Requirements 3.5**
  
  - [x] 3.4 Write unit tests for phone validation edge cases
    - Test invalid formats: too short, too long, wrong prefix, non-numeric
    - _Requirements: 3.1, 3.2, 3.3_

- [x] 4. Implement data sanitization for logging
  - [x] 4.1 Implement sanitizeForLogging method
    - Mask phone numbers to show first 3 and last 2 digits (233****67)
    - Remove clientSecret from any data structure
    - Preserve other data for debugging
    - _Requirements: 1.6, 5.3, 5.5, 5.6_
  
  - [x] 4.2 Write property test for phone number sanitization
    - **Property 13: Phone Number Sanitization in Logs**
    - **Validates: Requirements 1.6, 5.3**
  
  - [x] 4.3 Write property test for client secret never logged
    - **Property 14: Client Secret Never Logged**
    - **Validates: Requirements 5.5**
  
  - [x] 4.4 Write unit tests for sanitization edge cases
    - Test arrays with nested phone numbers
    - Test data structures with clientSecret
    - _Requirements: 5.3, 5.5, 5.6_

- [x] 5. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Implement response parsing
  - [x] 6.1 Implement parseResponse method
    - Parse JSON response body
    - Extract messageId (or messageIds), status, responseCode
    - Throw Exception with message "Invalid API response format" if invalid
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_
  
  - [x] 6.2 Write property test for successful response parsing
    - **Property 9: Successful Response Parsing**
    - **Validates: Requirements 1.4, 2.4, 6.2, 6.3, 6.4**
  
  - [x] 6.3 Write property test for invalid response format handling
    - **Property 11: Invalid Response Format Handling**
    - **Validates: Requirements 6.5**
  
  - [x] 6.4 Write property test for response round-trip
    - **Property 12: Response Round-Trip**
    - **Validates: Requirements 6.6**
  
  - [x] 6.5 Write unit tests for response parsing edge cases
    - Test missing fields, invalid JSON, empty responses
    - _Requirements: 6.5_

- [x] 7. Implement sendSingle method
  - [x] 7.1 Implement sendSingle method
    - Validate configuration
    - Validate phone number
    - Build request payload with From, To, Content
    - POST to {baseUrl}/send with Basic Auth
    - Parse and return response
    - Log success with sanitized data
    - Handle errors with logging
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 5.1, 5.2, 5.4_
  
  - [x] 7.2 Write property test for single SMS request structure
    - **Property 7: Single SMS Request Structure**
    - **Validates: Requirements 1.1, 1.2**
  
  - [x] 7.3 Write property test for error response handling
    - **Property 10: Error Response Handling**
    - **Validates: Requirements 1.5, 2.5**
  
  - [x] 7.4 Write property test for connection error handling
    - **Property 17: Connection Error Handling**
    - **Validates: Requirements 5.2**
  
  - [x] 7.5 Write unit tests for sendSingle
    - Test successful send with HTTP fake
    - Test API error responses (4xx, 5xx)
    - Test connection failures
    - Verify logging with Log fake
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_

- [x] 8. Implement sendBatch method
  - [x] 8.1 Implement sendBatch method
    - Validate configuration
    - Validate all phone numbers in recipients array
    - Build request payload with From, Recipients, Content
    - POST to {baseUrl}/batch/simple/send with Basic Auth
    - Parse and return response with messageIds array
    - Log success with recipient count and sanitized data
    - Handle errors with logging
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 3.4, 5.1, 5.2, 5.4_
  
  - [x] 8.2 Write property test for batch phone validation
    - **Property 2: Batch Phone Validation**
    - **Validates: Requirements 3.4**
  
  - [x] 8.3 Write property test for batch SMS request structure
    - **Property 8: Batch SMS Request Structure**
    - **Validates: Requirements 2.1, 2.2**
  
  - [x] 8.4 Write unit tests for sendBatch
    - Test successful batch send with HTTP fake
    - Test validation of multiple phone numbers
    - Test API error responses
    - Verify logging with recipient count
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 3.4_

- [x] 9. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 10. Write remaining property tests for logging
  - [x] 10.1 Write property test for sanitization method usage
    - **Property 15: Sanitization Method Usage**
    - **Validates: Requirements 5.6**
  
  - [x] 10.2 Write property test for error logging completeness
    - **Property 16: Error Logging Completeness**
    - **Validates: Requirements 5.1**
  
  - [x] 10.3 Write property test for success logging completeness
    - **Property 18: Success Logging Completeness**
    - **Validates: Requirements 5.4**

- [x] 11. Replace old SMSService with HubtelSmsService
  - [x] 11.1 Find all usages of SMSService in the codebase
    - Search for `use App\Services\SMSService` and `SMSService::` references
    - Document all files that need updates
    - _Requirements: All (migration task)_
  
  - [x] 11.2 Update service container bindings if any exist
    - Check bootstrap/providers.php and any service providers
    - Update bindings to use HubtelSmsService
    - _Requirements: All (migration task)_
  
  - [x] 11.3 Update all controller and service usages
    - Replace SMSService with HubtelSmsService
    - Update method calls: send() becomes sendSingle(), handle return type changes
    - Update sendOTP() calls to use sendSingle() with formatted message
    - _Requirements: All (migration task)_
  
  - [x] 11.4 Delete old SMSService and its test file
    - Delete app/Services/SMSService.php
    - Delete tests/Unit/Services/SMSServiceTest.php
    - _Requirements: All (migration task)_
  
  - [x] 11.5 Write integration tests for updated controllers
    - Test controllers that use HubtelSmsService
    - Verify SMS sending in real workflows (OTP, notifications)
    - _Requirements: All (migration task)_

- [x] 12. Final validation and code formatting
  - [x] 12.1 Run all tests to ensure nothing is broken
    - Run `php artisan test --compact`
    - Fix any failing tests
    - _Requirements: All_
  
  - [x] 12.2 Format code with Pint
    - Run `vendor/bin/pint --dirty --format agent`
    - _Requirements: All_

- [x] 13. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Property tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases
- The implementation mirrors HubtelService patterns for consistency
- All 18 correctness properties from the design are covered by property tests
