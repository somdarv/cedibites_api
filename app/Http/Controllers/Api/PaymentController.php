<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\HubtelPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(protected HubtelPaymentService $hubtelService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['order.customer.user']);

        if ($request->has('payment_status')) {
            $query->where('payments.payment_status', $request->payment_status);
        }

        if ($request->has('payment_method')) {
            $query->where('payments.payment_method', $request->payment_method);
        }

        if ($request->has('branch_id')) {
            $query->whereHas('order', fn ($q) => $q->where('branch_id', $request->branch_id));
        }

        if ($request->has('date_from')) {
            $query->whereDate('payments.created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('payments.created_at', '<=', $request->date_to);
        }

        $payments = $query->latest()->paginate($request->per_page ?? 15);

        return response()->success(
            PaymentResource::collection($payments)->response()->getData(true),
            'Payments retrieved successfully.'
        );
    }

    /**
     * Return aggregated stats for the transactions dashboard cards.
     */
    public function stats(Request $request): JsonResponse
    {
        $query = Payment::query();

        if ($request->has('branch_id')) {
            $query->whereHas('order', fn ($q) => $q->where('branch_id', $request->branch_id));
        }

        if ($request->has('date_from')) {
            $query->whereDate('payments.created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('payments.created_at', '<=', $request->date_to);
        }

        $rows = (clone $query)
            ->selectRaw('payment_status, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('payment_status')
            ->get()
            ->keyBy('payment_status');

        // For no_charge, sum the order total_amount (payment amount is always 0)
        $noChargeOrderTotal = (clone $query)
            ->where('payments.payment_status', 'no_charge')
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->sum('orders.total_amount');

        $stat = fn (string $status) => [
            'count' => (int) ($rows[$status]->count ?? 0),
            'total' => (float) ($rows[$status]->total ?? 0),
        ];

        return response()->success([
            'completed' => $stat('completed'),
            'pending' => $stat('pending'),
            'no_charge' => [
                'count' => (int) ($rows['no_charge']->count ?? 0),
                'total' => (float) $noChargeOrderTotal,
            ],
        ], 'Payment stats retrieved successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Payment $payment): JsonResponse
    {
        return response()->success(
            new PaymentResource($payment->load(['order.customer.user'])),
            'Payment retrieved successfully.'
        );
    }

    /**
     * Process refund for a payment.
     */
    public function refund(Request $request, Payment $payment): JsonResponse
    {
        if ($payment->payment_status !== 'completed') {
            return response()->error('Only completed payments can be refunded.', 422);
        }

        // TODO: Implement actual refund logic with payment gateway
        $payment->update([
            'payment_status' => 'refunded',
        ]);

        activity('payments')
            ->causedBy($request->user())
            ->performedOn($payment)
            ->withProperties([
                'order_number' => $payment->order->order_number,
                'amount' => (float) $payment->amount,
            ])
            ->event('refunded')
            ->log("Payment refunded for order {$payment->order->order_number}");

        return response()->success(
            new PaymentResource($payment->fresh(['order.customer.user'])),
            'Payment refunded successfully.'
        );
    }

    /**
     * Initiate a Hubtel payment for an order
     */
    public function initiateHubtelPayment(Request $request, Order $order): JsonResponse
    {
        // Validate request (Laravel will automatically return 422 on validation failure)
        $validated = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'regex:/^233[0-9]{9}$/'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'description' => ['required', 'string', 'max:500'],
        ], [
            'customer_name.string' => 'Customer name must be a valid string',
            'customer_name.max' => 'Customer name cannot exceed 255 characters',
            'customer_phone.string' => 'Customer phone must be a valid string',
            'customer_phone.regex' => 'Customer phone format is invalid. Use format: 233XXXXXXXXX',
            'customer_email.email' => 'Customer email must be a valid email address',
            'customer_email.max' => 'Customer email cannot exceed 255 characters',
            'description.required' => 'Payment description is required',
            'description.string' => 'Payment description must be a valid string',
            'description.max' => 'Payment description cannot exceed 500 characters',
        ]);

        // Check if order is payable
        $existingPayment = $order->payments()->where('payment_status', 'completed')->first();
        if ($existingPayment) {
            return response()->error('Order has already been paid', 409);
        }

        // For authenticated users, verify order ownership
        if ($request->user()) {
            $userCustomerId = $request->user()->customer?->id;
            if ($order->customer_id !== $userCustomerId) {
                return response()->error('Unauthorized', 403);
            }
        }

        try {
            // Use order contact info for guest customers or if not provided
            $customerName = $validated['customer_name'] ?? $order->contact_name;
            $customerPhone = $validated['customer_phone'] ?? $order->contact_phone;
            $customerEmail = $validated['customer_email'] ?? null;

            $result = $this->hubtelService->initializeTransaction([
                'order' => $order,
                'description' => $validated['description'],
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_email' => $customerEmail,
            ]);

            return response()->success(
                new PaymentResource($result['payment']),
                'Payment initiated successfully'
            );
        } catch (\RuntimeException $e) {
            return response()->error('Payment gateway configuration error', 500);
        } catch (\Exception $e) {
            return response()->error($e->getMessage(), 400);
        }
    }

    /**
     * Handle payment callback from Hubtel
     */
    public function hubtelCallback(Request $request): JsonResponse
    {
        if (! $this->isAllowedCallbackIp($request)) {
            \Illuminate\Support\Facades\Log::warning('Hubtel callback rejected: IP not in allowlist', [
                'ip' => $request->ip(),
            ]);

            return response()->error('Forbidden', 403);
        }

        try {
            $this->hubtelService->handleCallback($request->all());

            return response()->success(null, 'Callback processed successfully');
        } catch (\Exception $e) {
            return response()->error('Callback processing failed', 400);
        }
    }

    /**
     * Handle Direct Receive Money callback from Hubtel RMP (POS mobile money)
     */
    public function hubtelRmpCallback(Request $request): JsonResponse
    {
        if (! $this->isAllowedCallbackIp($request)) {
            \Illuminate\Support\Facades\Log::warning('Hubtel RMP callback rejected: IP not in allowlist', [
                'ip' => $request->ip(),
            ]);

            return response()->error('Forbidden', 403);
        }

        try {
            $this->hubtelService->handleRmpCallback($request->all());

            return response()->success(null, 'RMP callback processed successfully');
        } catch (\Exception $e) {
            return response()->error('RMP callback processing failed', 400);
        }
    }

    /**
     * Check whether the incoming request originates from an allowed Hubtel IP.
     *
     * When HUBTEL_ALLOWED_IPS is not configured (local/dev), all IPs are allowed.
     * In production, set HUBTEL_ALLOWED_IPS to a comma-separated list of Hubtel's
     * callback IP addresses to enforce strict allowlisting.
     */
    private function isAllowedCallbackIp(Request $request): bool
    {
        $allowedIps = config('services.hubtel.allowed_ips');

        if (empty($allowedIps)) {
            return true;
        }

        $allowed = array_map('trim', explode(',', $allowedIps));

        return in_array($request->ip(), $allowed, true);
    }

    /**
     * Manually verify payment status
     */
    public function verifyPayment(Payment $payment): JsonResponse
    {
        // If the payment is already in a terminal state (completed/failed/refunded),
        // return the local record directly without calling the external API.
        // This covers RMP payments where the callback has already updated the status.
        if (in_array($payment->payment_status, ['completed', 'failed', 'refunded'])) {
            return response()->success(
                new PaymentResource($payment->fresh()),
                'Payment verified successfully'
            );
        }

        try {
            $order = $payment->order;
            $result = $this->hubtelService->verifyTransaction($order->order_number);

            return response()->success(
                new PaymentResource($result['payment']),
                'Payment verified successfully'
            );
        } catch (\Exception $e) {
            return response()->error($e->getMessage(), 400);
        }
    }

    /**
     * Initiate a refund for a completed order payment.
     */
    public function refundOrder(Request $request, \App\Models\Order $order): JsonResponse
    {
        $payment = $order->payments()->where('payment_status', 'completed')->latest()->first();

        if (! $payment) {
            return response()->error('No completed payment found for this order', 422);
        }

        if ($payment->payment_status === 'refunded') {
            return response()->error('Payment has already been refunded', 422);
        }

        try {
            // Mark as refunded — Hubtel refund API integration can be added later
            $payment->update([
                'payment_status' => 'refunded',
                'refunded_at' => now(),
            ]);

            \Illuminate\Support\Facades\Log::info('Order refunded', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
            ]);

            activity()
                ->causedBy($request->user())
                ->performedOn($order)
                ->withProperties(['payment_id' => $payment->id, 'amount' => $payment->amount])
                ->log("Refund initiated for order {$order->order_number}");

            return response()->success(
                new PaymentResource($payment->fresh()),
                'Refund initiated successfully'
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Refund failed', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return response()->error('Failed to process refund', 500);
        }
    }
}
