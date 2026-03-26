# UAT Preparation for Hubtel Integration

This document provides all the requirements and commands needed for the UAT meeting with Hubtel.

## 1. Meeting to Test Services from End User Perspective

### Test Scenarios to Demonstrate

#### A. Online Checkout Payment (Customer-facing)
**Endpoint**: `POST /api/orders/{order}/payments/hubtel/initiate`

**Test Command (Local)**:
```bash
# Create a test order first (if needed)
php artisan tinker
# In tinker:
$order = Order::factory()->create(['total_amount' => 50.00, 'payment_status' => 'pending']);
exit

# Initiate payment
curl -X POST http://cedibites-api.test/api/orders/{order_id}/payments/hubtel/initiate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "customer_name": "John Doe",
    "customer_phone": "233241234567",
    "customer_email": "john@example.com",
    "description": "Payment for Order #ORD-123456"
  }'
```

**Expected Response**:
```json
{
  "success": true,
  "message": "Payment initiated successfully",
  "data": {
    "id": 1,
    "payment_method": "hubtel",
    "payment_status": "pending",
    "checkout_url": "https://checkout.hubtel.com/...",
    "checkout_direct_url": "https://checkout.hubtel.com/direct/..."
  }
}
```

**User Flow**:
1. Customer places order
2. System initiates Hubtel payment
3. Customer redirected to Hubtel checkout page
4. Customer selects payment method (MTN, Vodafone, Card, etc.)
5. Customer completes payment
6. Hubtel sends callback to our system
7. Order status updated automatically

#### B. POS Mobile Money Payment (Direct Receive Money)
**Endpoint**: `POST /api/pos/orders/{order}/payments/momo/initiate`

**Test Command (Local)**:
```bash
# Initiate POS mobile money payment
curl -X POST http://cedibites-api.test/api/pos/orders/{order_id}/payments/momo/initiate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_EMPLOYEE_TOKEN" \
  -d '{
    "customer_phone": "233241234567",
    "description": "POS Payment for Order #ORD-123456"
  }'
```

**Expected Response**:
```json
{
  "success": true,
  "message": "Mobile money payment initiated",
  "data": {
    "id": 2,
    "payment_method": "momo",
    "payment_status": "pending",
    "transaction_id": "...",
    "prompt_message": "Customer will receive prompt on 233241234567"
  }
}
```

**User Flow**:
1. Cashier enters customer phone number
2. System sends mobile money prompt to customer
3. Customer approves on their phone
4. Hubtel sends callback
5. Payment confirmed automatically

#### C. Mobile Number Verification
**Test Command (Local)**:
```bash
# Verify if a mobile number is registered for mobile money
curl -X POST http://cedibites-api.test/api/pos/verify-momo \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_EMPLOYEE_TOKEN" \
  -d '{
    "phone": "233241234567"
  }'
```

**Expected Response**:
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

---

## 2. Sample Callbacks from Hubtel

### A. Online Checkout Callback (Success)
**Endpoint**: `POST /api/payments/hubtel/callback`

**Sample Payload**:
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

**How to Test Locally**:
```bash
# Simulate Hubtel callback
curl -X POST http://cedibites-api.test/api/payments/hubtel/callback \
  -H "Content-Type: application/json" \
  -d '{
    "ResponseCode": "0000",
    "Status": "Success",
    "Data": {
      "CheckoutId": "test-checkout-id",
      "SalesInvoiceId": "INV-TEST-001",
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
  }'
```

### B. Online Checkout Callback (Failed)
```json
{
  "ResponseCode": "2001",
  "Status": "Failed",
  "Data": {
    "CheckoutId": "abc123xyz789",
    "ClientReference": "ORD-123456",
    "Status": "Unpaid",
    "Amount": 50.00
  }
}
```

### C. RMP (POS) Callback (Success)
**Endpoint**: `POST /api/payments/hubtel/rmp/callback`

**Sample Payload**:
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

**How to Test Locally**:
```bash
# Simulate RMP callback
curl -X POST http://cedibites-api.test/api/payments/hubtel/rmp/callback \
  -H "Content-Type: application/json" \
  -d '{
    "ResponseCode": "0000",
    "Data": {
      "TransactionId": "rmp-test-123",
      "ClientReference": "ORD-123456",
      "Amount": 50.00,
      "CustomerMsisdn": "233241234567",
      "Channel": "mtn-gh",
      "Status": "Success"
    }
  }'
```

### D. RMP (POS) Callback (Failed)
```json
{
  "ResponseCode": "2001",
  "Data": {
    "TransactionId": "rmp-txn-456",
    "ClientReference": "ORD-123456",
    "Amount": 50.00,
    "CustomerMsisdn": "233241234567",
    "Channel": "vodafone-gh",
    "Status": "Failed"
  }
}
```

---

## 3. Sample Transaction Status Check Response

### Manual Verification Endpoint
**Endpoint**: `GET /api/payments/{payment}/verify`

**Test Command (Local)**:
```bash
# Verify payment status manually
curl -X GET http://cedibites-api.test/api/payments/{payment_id}/verify \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Hubtel Status Check API Response (Success)
**API**: `GET https://api-txnstatus.hubtel.com/transactions/{merchantAccountNumber}/status?clientReference={order_number}`

**Sample Response**:
```json
{
  "transactionId": "abc123xyz789",
  "externalTransactionId": "EXT-MTN-456789",
  "amount": 50.00,
  "charges": 1.25,
  "status": "Paid",
  "clientReference": "ORD-123456",
  "description": "Payment for Order #ORD-123456",
  "customerPhoneNumber": "233241234567",
  "paymentType": "mobilemoney",
  "channel": "mtn-gh"
}
```

### Status Check Response (Pending)
```json
{
  "transactionId": "abc123xyz789",
  "amount": 50.00,
  "status": "Unpaid",
  "clientReference": "ORD-123456"
}
```

### Status Check Response (Failed)
```json
{
  "transactionId": "abc123xyz789",
  "amount": 50.00,
  "status": "Failed",
  "clientReference": "ORD-123456",
  "failureReason": "Insufficient funds"
}
```

**How to Test Locally**:
```bash
# This requires actual Hubtel credentials and IP whitelisting
# You can simulate by checking the payment record in database
php artisan tinker
# In tinker:
$payment = Payment::find(1);
$payment->payment_gateway_response; // View stored Hubtel response
```

---

## 4. Link to the App When Live

### Production URLs (To be provided when deployed)

**Customer App**:
- Production: `https://app.cedibites.com`
- Staging: `https://staging.cedibites.com`

**API Base URL**:
- Production: `https://api.cedibites.com`
- Staging: `https://staging-api.cedibites.com`

**Hubtel Callback URLs** (Must be whitelisted by Hubtel):
- Production Checkout Callback: `https://api.cedibites.com/api/payments/hubtel/callback`
- Production RMP Callback: `https://api.cedibites.com/api/payments/hubtel/rmp/callback`
- Staging Checkout Callback: `https://staging-api.cedibites.com/api/payments/hubtel/callback`
- Staging RMP Callback: `https://staging-api.cedibites.com/api/payments/hubtel/rmp/callback`

### Local Development URLs
- Local API: `http://cedibites-api.test`
- Local Frontend: `http://localhost:3000` (or as configured)

**Note**: For local testing, you'll need to use ngrok or similar tunneling service to expose your local API to Hubtel callbacks:
```bash
# Install ngrok if not already installed
brew install ngrok  # macOS

# Expose local API
ngrok http cedibites-api.test:80

# Use the ngrok URL for callback testing
# Example: https://abc123.ngrok.io/api/payments/hubtel/callback
```

---

## 5. Predesigned Flow Diagrams

### A. Customer Online Checkout Flow

```
┌─────────────┐
│  Customer   │
│ Places Order│
└──────┬──────┘
       │
       ▼
┌─────────────────────────────┐
│ Frontend: Initiate Payment  │
│ POST /api/orders/{id}/      │
│      payments/hubtel/initiate│
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Backend: Create Payment     │
│ - Status: pending           │
│ - Store order reference     │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Hubtel API: Initialize      │
│ POST /items/initiate        │
│ Returns: checkoutUrl        │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Frontend: Redirect Customer │
│ to Hubtel Checkout Page     │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Customer: Select Payment    │
│ - MTN Mobile Money          │
│ - Vodafone Cash             │
│ - Visa/Mastercard           │
│ - Hubtel Wallet, etc.       │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Customer: Complete Payment  │
│ - Enter PIN/Approve         │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Hubtel: Send Callback       │
│ POST /api/payments/hubtel/  │
│      callback               │
│ - ResponseCode: 0000        │
│ - Status: Success           │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Backend: Update Payment     │
│ - Status: completed         │
│ - Store callback data       │
│ - Update order status       │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Frontend: Show Success      │
│ Redirect to order details   │
└─────────────────────────────┘
```

### B. POS Mobile Money Flow (Direct Receive Money)

```
┌─────────────┐
│  Cashier    │
│ Creates Order│
└──────┬──────┘
       │
       ▼
┌─────────────────────────────┐
│ POS: Enter Customer Phone   │
│ 233241234567                │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Optional: Verify Number     │
│ POST /api/pos/verify-momo   │
│ - Check if registered       │
│ - Show account name         │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ POS: Initiate Payment       │
│ POST /api/pos/orders/{id}/  │
│      payments/momo/initiate │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Backend: Create Payment     │
│ - Status: pending           │
│ - Detect network (MTN/Voda) │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Hubtel RMP API: Send Prompt │
│ POST /receive-money/send    │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Customer Phone: Receives    │
│ Mobile Money Prompt         │
│ "Approve GHS 50.00 to       │
│  CediBites? *170#"          │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Customer: Enters PIN        │
│ and Approves                │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Hubtel: Send RMP Callback   │
│ POST /api/payments/hubtel/  │
│      rmp/callback           │
│ - ResponseCode: 0000        │
│ - Status: Success           │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Backend: Update Payment     │
│ - Status: completed         │
│ - Print receipt             │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ POS: Show Success           │
│ Order ready for fulfillment │
└─────────────────────────────┘
```

### C. Payment Verification Flow (Fallback)

```
┌─────────────────────────────┐
│ Scenario: Callback Not      │
│ Received (Network Issue)    │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Admin/System: Manual Verify │
│ GET /api/payments/{id}/verify│
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Backend: Check Local Status │
│ If already completed/failed,│
│ return immediately          │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Hubtel Status Check API     │
│ GET /transactions/{merchant}│
│     /status?clientReference │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Backend: Update Payment     │
│ based on Hubtel response    │
│ - Paid → completed          │
│ - Unpaid → pending          │
│ - Failed → failed           │
└──────┬──────────────────────┘
       │
       ▼
┌─────────────────────────────┐
│ Return Updated Status       │
└─────────────────────────────┘
```

---

## Additional Information for UAT

### Required Hubtel Configuration

**Environment Variables** (`.env`):
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

# Callback IP Allowlist (Production only)
HUBTEL_ALLOWED_IPS=154.160.17.142,154.160.17.143
```

### IP Whitelisting Requirements

**Our Server IPs** (to be whitelisted by Hubtel):
- Production API Server: `[TO BE PROVIDED]`
- Staging API Server: `[TO BE PROVIDED]`

**Hubtel Callback IPs** (to be whitelisted in our firewall):
- `154.160.17.142`
- `154.160.17.143`
- (Request complete list from Hubtel)

### Supported Payment Methods

1. **Mobile Money**:
   - MTN Mobile Money (mtn-gh)
   - Vodafone Cash (vodafone-gh)
   - AirtelTigo Money (airtel-gh, tigo-gh)

2. **Bank Cards**:
   - Visa
   - Mastercard

3. **Digital Wallets**:
   - Hubtel Wallet
   - G-Money
   - Zeepay

4. **Other**:
   - GhQR
   - Cash/Cheque

### Testing Checklist

- [ ] Online checkout payment initiation
- [ ] Customer completes payment on Hubtel page
- [ ] Callback received and processed correctly
- [ ] Payment status updated in database
- [ ] Order status updated after payment
- [ ] POS mobile money payment initiation
- [ ] Customer receives and approves prompt
- [ ] RMP callback received and processed
- [ ] Mobile number verification works
- [ ] Manual payment verification works
- [ ] Failed payment handling
- [ ] Callback IP allowlisting works
- [ ] All payment methods tested (MTN, Vodafone, Card)
- [ ] Error handling and logging verified

### Database Queries for Verification

```bash
# Check payment records
php artisan tinker
Payment::with('order')->latest()->take(10)->get();

# Check specific payment
Payment::where('transaction_id', 'CHECKOUT_ID')->first();

# View payment gateway responses
Payment::find(1)->payment_gateway_response;

# Check order payment status
Order::where('order_number', 'ORD-123456')->with('payments')->first();
```

### Logs to Monitor

```bash
# View application logs
tail -f storage/logs/laravel.log

# Filter Hubtel-related logs
tail -f storage/logs/laravel.log | grep -i hubtel

# View callback logs
tail -f storage/logs/laravel.log | grep -i callback
```

---

## Contact Information

**For UAT Meeting**:
- Date: [TO BE SCHEDULED]
- Attendees: [YOUR TEAM] + Hubtel Integration Team
- Duration: 1-2 hours

**Hubtel Support**:
- Email: support@hubtel.com
- Phone: +233 (0) 30 281 0100
- Documentation: https://developers.hubtel.com

**Our Team**:
- Technical Lead: [NAME]
- Backend Developer: [NAME]
- QA Engineer: [NAME]
