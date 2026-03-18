# Requirements Document

## Introduction

The HubtelSmsService provides SMS messaging capabilities for the CediBites application using the Hubtel SMS API. This service replaces the existing SMSService with a focused implementation for sending single and batch SMS messages to customers in Ghana. The service handles phone number validation, API authentication, error handling, and logging following the same patterns established by the existing HubtelService for payments.

## Glossary

- **HubtelSmsService**: The Laravel service class that interfaces with the Hubtel SMS API
- **Hubtel_SMS_API**: The external Hubtel SMS REST API (https://sms.hubtel.com/v1/messages)
- **SMS_Message**: A text message containing From (sender ID), To (recipient phone), and Content (message text)
- **Batch_SMS**: Multiple SMS messages with the same content sent to different recipients in a single API call
- **Ghana_Phone_Number**: A phone number in the format 233XXXXXXXXX (country code 233 followed by 9 digits)
- **Basic_Auth**: HTTP authentication using clientId:clientSecret encoded in base64
- **Message_ID**: Unique identifier returned by Hubtel for each sent SMS
- **Response_Code**: Status code returned by Hubtel indicating success or failure

## Requirements

### Requirement 1: Send Single SMS

**User Story:** As a developer, I want to send a single SMS message to a customer, so that I can notify them about orders, payments, or OTP codes.

#### Acceptance Criteria

1. THE HubtelSmsService SHALL send a single SMS message via POST to the Hubtel_SMS_API /send endpoint
2. WHEN sending a single SMS, THE HubtelSmsService SHALL include From (sender ID), To (recipient phone), and Content (message text) in the request payload
3. WHEN sending a single SMS, THE HubtelSmsService SHALL authenticate using Basic_Auth with clientId and clientSecret
4. WHEN the Hubtel_SMS_API returns a successful response, THE HubtelSmsService SHALL return the Message_ID and Response_Code
5. WHEN the Hubtel_SMS_API returns an error response, THE HubtelSmsService SHALL throw an exception with a descriptive error message
6. THE HubtelSmsService SHALL log all SMS sending attempts with sanitized phone numbers (first 3 and last 2 digits visible)

### Requirement 2: Send Batch SMS

**User Story:** As a developer, I want to send the same SMS message to multiple customers at once, so that I can efficiently notify multiple recipients about promotions or updates.

#### Acceptance Criteria

1. THE HubtelSmsService SHALL send batch SMS messages via POST to the Hubtel_SMS_API /batch/simple/send endpoint
2. WHEN sending batch SMS, THE HubtelSmsService SHALL include From (sender ID), Recipients (array of phone numbers), and Content (message text) in the request payload
3. WHEN sending batch SMS, THE HubtelSmsService SHALL authenticate using Basic_Auth with clientId and clientSecret
4. WHEN the Hubtel_SMS_API returns a successful response, THE HubtelSmsService SHALL return an array of Message_ID values for each recipient
5. WHEN the Hubtel_SMS_API returns an error response, THE HubtelSmsService SHALL throw an exception with a descriptive error message
6. THE HubtelSmsService SHALL log batch SMS attempts with the count of recipients and sanitized phone numbers

### Requirement 3: Validate Phone Numbers

**User Story:** As a developer, I want phone numbers to be validated before sending SMS, so that I can prevent API errors and ensure messages reach valid Ghanaian numbers.

#### Acceptance Criteria

1. THE HubtelSmsService SHALL validate that phone numbers match the Ghana_Phone_Number format (233XXXXXXXXX)
2. WHEN a phone number does not start with "233", THE HubtelSmsService SHALL throw an exception with message "Invalid phone number format"
3. WHEN a phone number does not have exactly 12 digits, THE HubtelSmsService SHALL throw an exception with message "Invalid phone number format"
4. WHEN validating batch SMS recipients, THE HubtelSmsService SHALL validate each phone number in the Recipients array
5. THE HubtelSmsService SHALL accept phone numbers as strings to preserve leading zeros

### Requirement 4: Configuration Management

**User Story:** As a developer, I want SMS service configuration to be managed through Laravel's config system, so that I can easily configure credentials and settings per environment.

#### Acceptance Criteria

1. THE HubtelSmsService SHALL read clientId from config('services.hubtel.client_id')
2. THE HubtelSmsService SHALL read clientSecret from config('services.hubtel.client_secret')
3. THE HubtelSmsService SHALL read sender ID from config('services.hubtel.sender_id') with default value "CediBites"
4. THE HubtelSmsService SHALL read base URL from config('services.hubtel.sms_base_url') with default value "https://sms.hubtel.com/v1/messages"
5. WHEN clientId or clientSecret is empty, THE HubtelSmsService SHALL throw a RuntimeException with message "Hubtel SMS is not properly configured"
6. THE HubtelSmsService SHALL validate configuration before making any API calls

### Requirement 5: Error Handling and Logging

**User Story:** As a developer, I want comprehensive error handling and logging, so that I can troubleshoot SMS delivery issues and monitor service health.

#### Acceptance Criteria

1. WHEN the Hubtel_SMS_API returns a non-successful HTTP status, THE HubtelSmsService SHALL log the error with endpoint, status code, and response body
2. WHEN an HTTP connection exception occurs, THE HubtelSmsService SHALL log the error and throw an exception with message "Failed to connect to Hubtel SMS API"
3. THE HubtelSmsService SHALL sanitize phone numbers in all log entries by masking middle digits (show first 3 and last 2 digits)
4. THE HubtelSmsService SHALL log successful SMS sends with Message_ID, recipient count, and timestamp
5. THE HubtelSmsService SHALL never log clientSecret in any log entry
6. WHEN logging request payloads, THE HubtelSmsService SHALL use a sanitizeForLogging method to mask sensitive data

### Requirement 6: Parser and Serializer for API Responses

**User Story:** As a developer, I want API responses to be properly parsed and validated, so that I can reliably extract message IDs and status codes from Hubtel responses.

#### Acceptance Criteria

1. WHEN the Hubtel_SMS_API returns a response, THE HubtelSmsService SHALL parse the JSON response body
2. THE HubtelSmsService SHALL extract messageId from the response data structure
3. THE HubtelSmsService SHALL extract status from the response data structure
4. THE HubtelSmsService SHALL extract responseCode from the response data structure
5. WHEN the response JSON is invalid or missing required fields, THE HubtelSmsService SHALL throw an exception with message "Invalid API response format"
6. FOR ALL valid Hubtel SMS API responses, parsing the response then formatting it back to JSON then parsing again SHALL produce an equivalent data structure (round-trip property)

