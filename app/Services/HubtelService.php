<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class HubtelService
{
    protected ?string $clientId;

    protected ?string $clientSecret;

    protected ?string $merchantAccountNumber;

    protected string $baseUrl;

    protected string $statusCheckUrl;

    public function __construct()
    {
        $this->clientId = config('services.hubtel.payment_client_id');
        $this->clientSecret = config('services.hubtel.payment_client_secret');
        $this->merchantAccountNumber = config('services.hubtel.merchant_account_number');
        $this->baseUrl = config('services.hubtel.base_url', 'https://payproxyapi.hubtel.com');
        $this->statusCheckUrl = config('services.hubtel.status_check_url', 'https://api-txnstatus.hubtel.com');
    }

    /**
     * Validate that Hubtel credentials are configured
     *
     * @throws RuntimeException
     */
    protected function validateConfiguration(): void
    {
        if (empty($this->clientId) || empty($this->clientSecret) || empty($this->merchantAccountNumber)) {
            throw new RuntimeException(
                'Hubtel payment gateway is not properly configured. '.
                'Please set HUBTEL_CLIENT_ID, HUBTEL_CLIENT_SECRET, and HUBTEL_MERCHANT_ACCOUNT_NUMBER in your environment.'
            );
        }
    }

    /**
     * Build Basic Auth header value
     *
     * @return string Base64 encoded credentials
     */
    protected function getAuthHeader(): string
    {
        return 'Basic '.base64_encode($this->clientId.':'.$this->clientSecret);
    }

    /**
     * Map Hubtel response code to descriptive error message
     *
     * @param  string  $responseCode  Hubtel response code
     * @return string Human-readable error message
     */
    protected function mapResponseCodeToMessage(string $responseCode): string
    {
        return match ($responseCode) {
            '0000' => 'Payment successful',
            '0005' => 'Payment processor error or network issue. Please try again.',
            '2001' => 'Transaction failed. Please try again or use a different payment method.',
            '4000' => 'Invalid payment data. Please check your information and try again.',
            '4070' => 'Payment amount issue. Please contact support.',
            default => 'An unexpected error occurred. Please try again or contact support.',
        };
    }

    /**
     * Map Hubtel status to application payment status
     *
     * @param  string  $hubtelStatus  Status from Hubtel (Success, Paid, Unpaid, Refunded)
     * @param  string  $responseCode  Response code from Hubtel
     * @return string Application payment status
     */
    protected function mapHubtelStatusToPaymentStatus(string $hubtelStatus, string $responseCode): string
    {
        // Failed response code overrides status
        if ($responseCode !== '0000') {
            return 'failed';
        }

        return match (strtolower($hubtelStatus)) {
            'success', 'paid' => 'completed',
            'unpaid' => 'pending',
            'refunded' => 'refunded',
            default => 'pending',
        };
    }

    /**
     * Execute HTTP request with retry logic
     *
     * @param  callable  $request  The HTTP request to execute
     * @param  int  $maxRetries  Maximum retry attempts
     *
     * @throws \Exception When all retries are exhausted
     */
    protected function executeWithRetry(callable $request, int $maxRetries = 3): \Illuminate\Http\Client\Response
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                return $request();
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt < $maxRetries) {
                    // Exponential backoff: 1s, 2s, 4s
                    $delay = pow(2, $attempt - 1);
                    sleep($delay);

                    Log::warning('Hubtel API request failed, retrying', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'delay' => $delay,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::error('Hubtel API request failed after all retries', [
            'attempts' => $attempt,
            'error' => $lastException->getMessage(),
        ]);

        throw new \Exception('Failed to connect to Hubtel payment gateway. Please try again later.');
    }

    /**
     * Sanitize data for logging by masking sensitive information
     *
     * @param  array  $data  Data to sanitize
     * @return array Sanitized data
     */
    protected function sanitizeForLogging(array $data): array
    {
        $sanitized = $data;

        // Remove sensitive fields
        unset($sanitized['client_secret']);

        // Mask phone numbers (show first 3 and last 2 digits)
        if (isset($sanitized['customer_phone'])) {
            $phone = $sanitized['customer_phone'];
            if (strlen($phone) > 5) {
                $sanitized['customer_phone'] = substr($phone, 0, 3).'****'.substr($phone, -2);
            }
        }

        if (isset($sanitized['payeeMobileNumber'])) {
            $phone = $sanitized['payeeMobileNumber'];
            if (strlen($phone) > 5) {
                $sanitized['payeeMobileNumber'] = substr($phone, 0, 3).'****'.substr($phone, -2);
            }
        }

        if (isset($sanitized['CustomerPhoneNumber'])) {
            $phone = $sanitized['CustomerPhoneNumber'];
            if (strlen($phone) > 5) {
                $sanitized['CustomerPhoneNumber'] = substr($phone, 0, 3).'****'.substr($phone, -2);
            }
        }

        // Mask emails (show first 3 chars and domain)
        if (isset($sanitized['customer_email'])) {
            $email = $sanitized['customer_email'];
            $parts = explode('@', $email);
            if (count($parts) === 2) {
                $sanitized['customer_email'] = substr($parts[0], 0, 3).'***@'.$parts[1];
            }
        }

        if (isset($sanitized['payeeEmail'])) {
            $email = $sanitized['payeeEmail'];
            $parts = explode('@', $email);
            if (count($parts) === 2) {
                $sanitized['payeeEmail'] = substr($parts[0], 0, 3).'***@'.$parts[1];
            }
        }

        return $sanitized;
    }

    /**
     * Initialize a payment transaction with Hubtel
     *
     * @param  array  $data  Payment initialization data
     * @return array Normalized response with checkout URLs
     *
     * @throws \Exception When initialization fails
     */
    public function initializeTransaction(array $data): array
    {
        $this->validateConfiguration();

        $order = $data['order'];

        // Build Hubtel API request payload
        $payload = [
            'totalAmount' => $order->total_amount,
            'description' => $data['description'],
            'callbackUrl' => route('payments.hubtel.callback'),
            'returnUrl' => config('app.frontend_url')."/orders/{$order->order_number}/payment/success",
            'cancellationUrl' => config('app.frontend_url')."/orders/{$order->order_number}/payment/cancelled",
            'merchantAccountNumber' => $this->merchantAccountNumber,
            'clientReference' => substr($order->order_number, 0, 32), // Max 32 characters
        ];

        // Include customer details - use provided data or fall back to order contact info for guest customers
        if (! empty($data['customer_name'])) {
            $payload['payeeName'] = $data['customer_name'];
        } elseif (! empty($order->contact_name)) {
            $payload['payeeName'] = $order->contact_name;
        }

        if (! empty($data['customer_phone'])) {
            $payload['payeeMobileNumber'] = $data['customer_phone'];
        } elseif (! empty($order->contact_phone)) {
            $payload['payeeMobileNumber'] = $order->contact_phone;
        }

        if (! empty($data['customer_email'])) {
            $payload['payeeEmail'] = $data['customer_email'];
        }

        // Log payment initiation with sanitized data
        Log::info('Hubtel payment initiation started', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'amount' => $order->total_amount,
            'client_reference' => $payload['clientReference'],
            'payload' => $this->sanitizeForLogging($payload),
        ]);

        // Send POST request to Hubtel initiate endpoint
        $response = Http::withHeaders([
            'Authorization' => $this->getAuthHeader(),
        ])->post($this->baseUrl.'/items/initiate', $payload);

        if (! $response->successful()) {
            $responseCode = $response->json('ResponseCode', 'unknown');
            $message = $this->mapResponseCodeToMessage($responseCode);

            Log::error('Hubtel payment initiation failed', [
                'order_id' => $order->id,
                'endpoint' => $this->baseUrl.'/items/initiate',
                'response_code' => $responseCode,
                'message' => $message,
                'status_code' => $response->status(),
            ]);

            throw new \Exception($message);
        }

        $responseData = $response->json('data', []);

        // Create Payment record
        $payment = Payment::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id, // Can be null for guest customers
            'payment_method' => 'mobile_money', // Default, will be updated by callback with actual method
            'payment_status' => 'pending',
            'amount' => $order->total_amount,
            'transaction_id' => $responseData['checkoutId'] ?? null,
            'payment_gateway_response' => $responseData,
        ]);

        Log::info('Hubtel payment initiated', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'amount' => $order->total_amount,
            'checkout_id' => $responseData['checkoutId'] ?? null,
        ]);

        // Return normalized response
        return [
            'payment' => $payment,
            'checkoutUrl' => $responseData['checkoutUrl'] ?? null,
            'checkoutDirectUrl' => $responseData['checkoutDirectUrl'] ?? null,
            'checkoutId' => $responseData['checkoutId'] ?? null,
            'clientReference' => $responseData['clientReference'] ?? $payload['clientReference'],
        ];
    }

    /**
     * Handle payment callback from Hubtel
     *
     * @param  array  $payload  The callback payload from Hubtel
     *
     * @throws \Exception When callback processing fails
     */
    public function handleCallback(array $payload): void
    {
        $this->validateConfiguration();

        try {
            // Parse callback JSON payload
            $responseCode = $payload['ResponseCode'] ?? null;
            $status = $payload['Status'] ?? null;
            $data = $payload['Data'] ?? [];

            if (empty($responseCode) || empty($status) || empty($data)) {
                Log::error('Hubtel callback missing required fields', [
                    'payload' => $this->sanitizeForLogging($payload),
                ]);
                throw new \Exception('Invalid callback payload');
            }

            // Extract payment details from Data object
            $checkoutId = $data['CheckoutId'] ?? null;
            $salesInvoiceId = $data['SalesInvoiceId'] ?? null;
            $clientReference = $data['ClientReference'] ?? null;
            $amount = $data['Amount'] ?? null;
            $customerPhoneNumber = $data['CustomerPhoneNumber'] ?? null;
            $paymentDetails = $data['PaymentDetails'] ?? [];

            if (empty($clientReference)) {
                Log::error('Hubtel callback missing clientReference', [
                    'payload' => $this->sanitizeForLogging($payload),
                ]);
                throw new \Exception('Missing clientReference in callback');
            }

            // Map PaymentType to payment_method
            $paymentType = $paymentDetails['PaymentType'] ?? null;
            $paymentMethod = match (strtolower($paymentType ?? '')) {
                'mobilemoney' => 'mobile_money',
                'card' => 'card',
                'wallet' => 'wallet',
                'ghqr' => 'ghqr',
                'cash' => 'cash',
                default => 'mobile_money', // Default fallback
            };

            // Find Payment record by clientReference (order_number)
            $payment = Payment::whereHas('order', function ($query) use ($clientReference) {
                $query->where('order_number', $clientReference);
            })->first();

            if (! $payment) {
                Log::error('Payment not found for clientReference', ['clientReference' => $clientReference]);
                throw new \Exception('Payment not found');
            }

            // Map Hubtel status to payment_status
            $paymentStatus = $this->mapHubtelStatusToPaymentStatus($status, $responseCode);

            // Prepare update data
            $updateData = [
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'payment_gateway_response' => $payload,
                'paid_at' => $paymentStatus === 'completed' ? now() : $payment->paid_at,
            ];

            // Handle refund status
            if ($paymentStatus === 'refunded') {
                $updateData['refunded_at'] = now();
                // Extract refund reason if provided in callback
                if (! empty($data['RefundReason'])) {
                    $updateData['refund_reason'] = $data['RefundReason'];
                }
            }

            // Update Payment record
            $payment->update($updateData);

            Log::info('Hubtel callback received', [
                'response_code' => $responseCode,
                'status' => $status,
                'checkout_id' => $checkoutId,
                'client_reference' => $clientReference,
                'payment_status' => $paymentStatus,
            ]);

            // Trigger order fulfillment if payment completed
            if ($paymentStatus === 'completed') {
                // TODO: Implement order fulfillment logic
                // This could be an event dispatch or direct method call
                Log::info('Payment completed, order fulfillment triggered', [
                    'order_id' => $payment->order_id,
                    'payment_id' => $payment->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Hubtel callback processing failed', [
                'error' => $e->getMessage(),
                'payload' => $this->sanitizeForLogging($payload),
            ]);
            throw $e;
        }
    }

    /**
     * Verify a transaction status via Hubtel Status Check API
     *
     * @param  string  $clientReference  The order number used as client reference
     * @return array Normalized transaction status
     *
     * @throws \Exception When verification fails
     */
    public function verifyTransaction(string $clientReference): array
    {
        $this->validateConfiguration();

        try {
            Log::info('Payment verification started', [
                'client_reference' => $clientReference,
                'timestamp' => now()->toIso8601String(),
            ]);

            // Send GET request to Status Check API
            $url = "{$this->statusCheckUrl}/transactions/{$this->merchantAccountNumber}/status";

            $response = Http::withHeaders([
                'Authorization' => $this->getAuthHeader(),
            ])->get($url, [
                'clientReference' => $clientReference,
            ]);

            if (! $response->successful()) {
                Log::error('Hubtel status check failed', [
                    'client_reference' => $clientReference,
                    'endpoint' => $url,
                    'status_code' => $response->status(),
                    'response' => $response->json(),
                ]);
                throw new \Exception('Failed to verify transaction status');
            }

            $data = $response->json();

            // Extract transaction details
            $transactionId = $data['transactionId'] ?? null;
            $externalTransactionId = $data['externalTransactionId'] ?? null;
            $amount = $data['amount'] ?? null;
            $charges = $data['charges'] ?? null;
            $hubtelStatus = $data['status'] ?? null;

            if (empty($hubtelStatus)) {
                throw new \Exception('Invalid status check response');
            }

            // Find Payment record by clientReference
            $payment = Payment::whereHas('order', function ($query) use ($clientReference) {
                $query->where('order_number', $clientReference);
            })->first();

            if (! $payment) {
                Log::error('Payment not found for verification', ['clientReference' => $clientReference]);
                throw new \Exception('Payment not found');
            }

            // Map Hubtel status to payment_status
            $paymentStatus = $this->mapHubtelStatusToPaymentStatus($hubtelStatus, '0000');

            // Update Payment record if status changed
            if ($payment->payment_status !== $paymentStatus) {
                $updateData = [
                    'payment_status' => $paymentStatus,
                    'payment_gateway_response' => array_merge(
                        $payment->payment_gateway_response ?? [],
                        ['status_check' => $data]
                    ),
                    'paid_at' => $paymentStatus === 'completed' ? now() : $payment->paid_at,
                ];

                // Handle refund status
                if ($paymentStatus === 'refunded') {
                    $updateData['refunded_at'] = now();
                    // Extract refund reason if provided in status check response
                    if (! empty($data['refundReason'])) {
                        $updateData['refund_reason'] = $data['refundReason'];
                    }
                }

                $payment->update($updateData);

                Log::info('Payment status updated via verification', [
                    'client_reference' => $clientReference,
                    'old_status' => $payment->payment_status,
                    'new_status' => $paymentStatus,
                ]);
            }

            Log::info('Payment verification completed', [
                'client_reference' => $clientReference,
                'transaction_id' => $transactionId,
                'status' => $hubtelStatus,
                'payment_status' => $paymentStatus,
                'timestamp' => now()->toIso8601String(),
            ]);

            // Return normalized transaction status
            return [
                'payment' => $payment->fresh(),
                'transactionId' => $transactionId,
                'externalTransactionId' => $externalTransactionId,
                'amount' => (float) $amount,
                'charges' => (float) $charges,
                'status' => $hubtelStatus,
                'paymentStatus' => $paymentStatus,
            ];
        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'client_reference' => $clientReference,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
