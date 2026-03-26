# CediBites x Hubtel Payment Integration Flow

## Presentation for UAT Meeting

---

## Slide 1: Overview

### CediBites Payment Integration with Hubtel

**Integration Type**: Dual Integration
- **Online Checkout** (Customer-facing web/mobile app)
- **POS Direct Receive Money** (In-store cashier system)

**Supported Payment Methods**:
- Mobile Money (MTN, Vodafone, AirtelTigo)
- Bank Cards (Visa, Mastercard)
- Digital Wallets (Hubtel, G-Money, Zeepay)
- GhQR, Cash/Cheque

---

## Slide 2: System Architecture

```
┌──────────────────────────────────────────────────────────┐
│                    CediBites Ecosystem                    │
│                                                           │
│  ┌─────────────────┐         ┌─────────────────┐        │
│  │  Customer App   │         │   POS System    │        │
│  │  (React/Next.js)│         │   (React)       │        │
│  └────────┬────────┘         └────────┬────────┘        │
│           │                           │                  │
│           └───────────┬───────────────┘                  │
│                       │                                  │
│                       ▼                                  │
│           ┌──────────────────────┐                      │
│           │   Laravel API        │                      │
│           │   (cedibites_api)    │                      │
│           │                      │                      │
│           │  - PaymentController │                      │
│           │  - HubtelPaymentService │                   │
│           │  - Payment Model     │                      │
│           └──────────┬───────────┘                      │
└──────────────────────┼──────────────────────────────────┘
                       │
                       │ HTTPS/REST API
                       │
┌──────────────────────┼──────────────────────────────────┐
│                      ▼                                   │
│              Hubtel Services                             │
│                                                          │
│  ┌──────────────────┐  ┌──────────────────┐           │
│  │ Online Checkout  │  │ Direct Receive   │           │
│  │ (payproxyapi)    │  │ Money (RMP)      │           │
│  └──────────────────┘  └──────────────────┘           │
│                                                          │
│  ┌──────────────────┐  ┌──────────────────┐           │
│  │ Status Check API │  │ Verification API │           │
│  │ (txnstatus)      │  │ (RNV)            │           │
│  └──────────────────┘  └──────────────────┘           │
└──────────────────────────────────────────────────────────┘
```

---

## Slide 3: Customer Online Checkout Flow

### Step-by-Step Process

**1. Customer Places Order**
- Selects items from menu
- Adds to cart
- Proceeds to checkout

**2. Payment Initiation**
```
POST /api/orders/{order}/payments/hubtel/initiate
{
  "customer_name": "John Doe",
  "customer_phone": "233241234567",
  "customer_email": "john@example.com",
  "description": "Payment for Order #ORD-123456"
}
```

**3. Backend Processing**
- Creates Payment record (status: pending)
- Calls Hubtel API with order details
- Receives checkout URL

**4. Customer Redirect**
- Frontend redirects to Hubtel checkout page
- Customer sees all available payment methods
- Customer selects preferred method (MTN, Vodafone, Card, etc.)

**5. Payment Completion**
- Customer enters PIN/card details
- Hubtel processes payment
- Hubtel sends callback to our API

**6. Callback Processing**
```
POST /api/payments/hubtel/callback
{
  "ResponseCode": "0000",
  "Status": "Success",
  "Data": {
    "CheckoutId": "abc123",
    "ClientReference": "ORD-123456",
    "Status": "Paid",
    "Amount": 50.00
  }
}
```

**7. Order Fulfillment**
- Payment status updated to "completed"
- Order status updated to "paid"
- Customer receives confirmation
- Kitchen receives order for preparation

---

## Slide 4: POS Mobile Money Flow

### In-Store Payment Process

**1. Cashier Creates Order**
- Scans/selects items
- Calculates total
- Customer chooses mobile money payment

**2. Phone Number Entry**
- Cashier enters customer's mobile number
- System detects network (MTN/Vodafone)
- Optional: Verify number is registered

**3. Payment Initiation**
```
POST /api/pos/orders/{order}/payments/momo/initiate
{
  "customer_phone": "233241234567",
  "description": "POS Payment for Order #ORD-123456"
}
```

**4. Backend Processing**
- Creates Payment record
- Auto-detects mobile network from phone prefix
- Calls Hubtel RMP API
- Sends mobile money prompt to customer

**5. Customer Approval**
- Customer receives prompt on phone
- "Approve GHS 50.00 to CediBites?"
- Customer enters PIN and approves

**6. RMP Callback**
```
POST /api/payments/hubtel/rmp/callback
{
  "ResponseCode": "0000",
  "Data": {
    "TransactionId": "rmp-123",
    "ClientReference": "ORD-123456",
    "Amount": 50.00,
    "Status": "Success"
  }
}
```

**7. Receipt & Fulfillment**
- Payment confirmed instantly
- Receipt printed
- Order sent to kitchen
- Customer waits for order

---

## Slide 5: Mobile Number Verification

### Pre-Payment Validation

**Purpose**: Verify customer's phone number is registered for mobile money before initiating payment

**API Endpoint**:
```
POST /api/pos/verify-momo
{
  "phone": "233241234567"
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "is_registered": true,
    "account_name": "John Doe",
    "network": "mtn-gh"
  }
}
```

**Benefits**:
- Reduces failed transactions
- Improves customer experience
- Shows account name for verification
- Detects network automatically

---

## Slide 6: Payment Verification (Fallback)

### Manual Status Check

**Scenario**: Callback not received due to network issues

**API Endpoint**:
```
GET /api/payments/{payment}/verify
```

**Process**:
1. Admin/System triggers manual verification
2. Backend checks local payment status
3. If still pending, queries Hubtel Status Check API
4. Updates payment based on Hubtel response
5. Returns current status

**Hubtel Status Check API**:
```
GET https://api-txnstatus.hubtel.com/transactions/{merchant}/status
    ?clientReference=ORD-123456
```

**Response Mapping**:
- `Paid` → Payment status: completed
- `Unpaid` → Payment status: pending
- `Failed` → Payment status: failed
- `Refunded` → Payment status: refunded

---

## Slide 7: API Endpoints Summary

### Customer-Facing Endpoints

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/orders/{order}/payments/hubtel/initiate` | POST | Optional | Initiate online checkout |
| `/api/payments/hubtel/callback` | POST | None | Receive payment callback |
| `/api/payments/{payment}/verify` | GET | Required | Manual verification |

### POS Endpoints

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/pos/orders/{order}/payments/momo/initiate` | POST | Required | Initiate POS mobile money |
| `/api/payments/hubtel/rmp/callback` | POST | None | Receive RMP callback |
| `/api/pos/verify-momo` | POST | Required | Verify mobile number |

### Admin Endpoints

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/payments` | GET | Required | List all payments |
| `/api/payments/stats` | GET | Required | Payment statistics |
| `/api/payments/{payment}` | GET | Required | View payment details |
| `/api/orders/{order}/refund` | POST | Required | Refund payment |

---

## Slide 8: Callback Handling

### Online Checkout Callback Structure

```json
{
  "ResponseCode": "0000",
  "Status": "Success",
  "Data": {
    "CheckoutId": "abc123xyz789",
    "SalesInvoiceId": "INV-2024-001",
    "ClientReference": "ORD-123456",
    "Status": "Paid",
    "Amount": 50.00,
    "CustomerPhoneNumber": "233241234567",
    "PaymentDetails": {
      "MobileMoneyNumber": "233241234567",
      "PaymentType": "mobilemoney",
      "Channel": "mtn-gh"
    }
  }
}
```

### RMP Callback Structure

```json
{
  "ResponseCode": "0000",
  "Data": {
    "TransactionId": "rmp-txn-123",
    "ClientReference": "ORD-123456",
    "Amount": 50.00,
    "CustomerMsisdn": "233241234567",
    "Channel": "mtn-gh",
    "Status": "Success"
  }
}
```

### Response Code Mapping

| Code | Meaning | Action |
|------|---------|--------|
| 0000 | Success | Mark payment as completed |
| 0005 | Processor error | Log error, keep pending |
| 2001 | Transaction failed | Mark payment as failed |
| 4000 | Validation error | Return error to user |
| 4070 | Fees issue | Return error to user |

---

## Slide 9: Data Storage

### Payment Model Structure

```php
Payment {
  id: integer
  order_id: integer
  customer_id: integer (nullable for guest orders)
  payment_method: enum (momo, cash_delivery, cash_pickup, hubtel)
  payment_status: enum (pending, completed, failed, refunded, cancelled, expired)
  amount: decimal
  transaction_id: string (Hubtel CheckoutId or TransactionId)
  payment_gateway_response: json (Complete Hubtel response)
  paid_at: timestamp
  refunded_at: timestamp
  created_at: timestamp
  updated_at: timestamp
}
```

### Stored Hubtel Response Example

```json
{
  "checkoutId": "abc123xyz789",
  "checkoutUrl": "https://checkout.hubtel.com/...",
  "ResponseCode": "0000",
  "Status": "Success",
  "Data": {
    "CheckoutId": "abc123xyz789",
    "SalesInvoiceId": "INV-2024-001",
    "ClientReference": "ORD-123456",
    "Status": "Paid",
    "Amount": 50.00,
    "PaymentDetails": {
      "PaymentType": "mobilemoney",
      "Channel": "mtn-gh"
    }
  }
}
```

---

## Slide 10: Security Measures

### Authentication & Authorization

**API Authentication**:
- Laravel Sanctum token-based auth
- Optional auth for guest checkout
- Required auth for POS operations

**Callback IP Allowlisting**:
```env
HUBTEL_ALLOWED_IPS=154.160.17.142,154.160.17.143
```
- Only requests from Hubtel IPs accepted
- Configurable per environment
- Disabled in local development

**Credential Management**:
- All credentials stored in `.env`
- Never exposed in API responses
- Sanitized in logs
- Different credentials for sandbox/production

### Data Protection

**Sensitive Data Handling**:
- Phone numbers masked in logs (233****67)
- Emails masked in logs (joh***@example.com)
- No card details stored locally
- PCI DSS compliance through Hubtel

**Audit Trail**:
- All payment operations logged
- Activity log for admin actions
- Complete Hubtel responses stored
- Timestamps for all status changes

---

## Slide 11: Error Handling

### Error Categories

**1. Configuration Errors**
- Missing credentials
- Invalid API URLs
- HTTP 500 response

**2. Validation Errors**
- Invalid phone format
- Missing required fields
- HTTP 422 response

**3. Hubtel API Errors**
- Network failures
- Timeout errors
- Response code errors
- HTTP 400/500 response

**4. Business Logic Errors**
- Order already paid
- Payment not found
- HTTP 404/409 response

### Retry Logic

**Network Error Handling**:
- Automatic retry up to 3 times
- Exponential backoff (1s, 2s, 4s)
- Detailed error logging
- User-friendly error messages

---

## Slide 12: Testing Strategy

### Test Scenarios

**Online Checkout**:
- ✓ Successful MTN payment
- ✓ Successful Vodafone payment
- ✓ Successful card payment
- ✓ Failed payment handling
- ✓ Cancelled payment handling
- ✓ Callback processing
- ✓ Guest customer checkout

**POS Mobile Money**:
- ✓ Successful MTN payment
- ✓ Successful Vodafone payment
- ✓ Failed payment handling
- ✓ Customer declines prompt
- ✓ RMP callback processing
- ✓ Number verification

**Edge Cases**:
- ✓ Callback not received (manual verification)
- ✓ Duplicate callbacks
- ✓ Invalid callback data
- ✓ Network timeout
- ✓ Concurrent payment attempts

---

## Slide 13: Monitoring & Logging

### Log Levels

**INFO**: Successful operations
```
Hubtel payment initiated: order_id=123, amount=50.00
Hubtel callback received: status=Success, checkout_id=abc123
Payment verified: payment_id=1, status=completed
```

**WARNING**: Retryable errors
```
Hubtel API request failed, retrying: attempt=1/3
Hubtel callback rejected: IP not in allowlist
```

**ERROR**: Failed operations
```
Hubtel API request failed after all retries
Payment initiation failed: order_id=123, error=...
Callback processing failed: invalid payload
```

### Monitoring Queries

```bash
# View recent payments
php artisan tinker
Payment::with('order')->latest()->take(10)->get();

# Check payment status
Payment::where('transaction_id', 'abc123')->first();

# View logs
tail -f storage/logs/laravel.log | grep -i hubtel
```

---

## Slide 14: Configuration Requirements

### Environment Variables

```env
# Online Checkout (payproxyapi)
HUBTEL_PAYMENT_CLIENT_ID=your_client_id
HUBTEL_PAYMENT_CLIENT_SECRET=your_client_secret
HUBTEL_MERCHANT_ACCOUNT_NUMBER=your_merchant_account

# Direct Receive Money (RMP)
HUBTEL_RMP_CLIENT_ID=your_rmp_client_id
HUBTEL_RMP_CLIENT_SECRET=your_rmp_client_secret

# Verification API (RNV)
HUBTEL_CLIENT_ID=your_verification_client_id
HUBTEL_CLIENT_SECRET=your_verification_client_secret

# API URLs (optional, defaults provided)
HUBTEL_BASE_URL=https://payproxyapi.hubtel.com
HUBTEL_RMP_BASE_URL=https://rmp.hubtel.com
HUBTEL_STATUS_CHECK_URL=https://api-txnstatus.hubtel.com
HUBTEL_RNV_BASE_URL=https://rnv.hubtel.com

# Security
HUBTEL_ALLOWED_IPS=154.160.17.142,154.160.17.143
```

### IP Whitelisting

**Our Server IPs** (to be whitelisted by Hubtel):
- Production: [TO BE PROVIDED]
- Staging: [TO BE PROVIDED]

**Hubtel Callback IPs** (whitelisted in our system):
- 154.160.17.142
- 154.160.17.143

---

## Slide 15: Deployment Checklist

### Pre-Deployment

- [ ] Hubtel credentials configured
- [ ] Callback URLs registered with Hubtel
- [ ] IP whitelisting completed (both sides)
- [ ] SSL certificates installed
- [ ] Database migrations run
- [ ] Environment variables set

### Testing

- [ ] Online checkout tested (all payment methods)
- [ ] POS mobile money tested (MTN, Vodafone)
- [ ] Callbacks received and processed
- [ ] Manual verification works
- [ ] Error handling verified
- [ ] Logs reviewed

### Post-Deployment

- [ ] Monitor callback success rate
- [ ] Review error logs daily
- [ ] Track payment completion rate
- [ ] Customer feedback collected
- [ ] Performance metrics tracked

---

## Slide 16: Support & Documentation

### Hubtel Resources

**Documentation**:
- Developer Portal: https://developers.hubtel.com
- API Reference: https://developers.hubtel.com/documentations
- Integration Guide: https://developers.hubtel.com/guides

**Support**:
- Email: support@hubtel.com
- Phone: +233 (0) 30 281 0100
- Business Hours: Mon-Fri, 8am-5pm GMT

### CediBites Resources

**Documentation**:
- API Documentation: `cedibites_api/api.json`
- Integration Specs: `cedibites_api/.kiro/specs/hubtel-payment-integration/`
- UAT Guide: `cedibites_api/UAT_PREPARATION.md`

**Code Repository**:
- Backend: `cedibites_api/`
- Service: `app/Services/HubtelPaymentService.php`
- Controller: `app/Http/Controllers/Api/PaymentController.php`

---

## Slide 17: Next Steps

### UAT Meeting Agenda

1. **Demo Online Checkout Flow** (15 mins)
   - Initiate payment
   - Complete on Hubtel page
   - Verify callback received

2. **Demo POS Mobile Money Flow** (15 mins)
   - Verify mobile number
   - Initiate payment
   - Customer approves
   - Verify RMP callback

3. **Review Callback Samples** (10 mins)
   - Success scenarios
   - Failure scenarios
   - Edge cases

4. **Discuss Status Check API** (10 mins)
   - Manual verification process
   - IP whitelisting requirements

5. **Review Integration Flow** (10 mins)
   - Architecture diagram
   - Data flow
   - Security measures

6. **Q&A and Action Items** (10 mins)

### Post-UAT Actions

- [ ] Address any issues identified
- [ ] Complete IP whitelisting
- [ ] Finalize production credentials
- [ ] Schedule go-live date
- [ ] Plan monitoring strategy

---

## Slide 18: Contact Information

### CediBites Team

**Technical Lead**: [NAME]
- Email: [EMAIL]
- Phone: [PHONE]

**Backend Developer**: [NAME]
- Email: [EMAIL]
- Phone: [PHONE]

**QA Engineer**: [NAME]
- Email: [EMAIL]
- Phone: [PHONE]

### Hubtel Integration Team

**Account Manager**: [TO BE PROVIDED]
**Technical Support**: support@hubtel.com
**Emergency Contact**: [TO BE PROVIDED]

---

## Thank You

### Questions?

**Prepared by**: CediBites Development Team
**Date**: [DATE]
**Version**: 1.0

**For more information**:
- Email: dev@cedibites.com
- Documentation: See UAT_PREPARATION.md
- Code: cedibites_api repository
