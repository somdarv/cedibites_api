<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\HubtelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(protected HubtelService $hubtelService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['order.customer.user']);

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->latest()->paginate($request->per_page ?? 15);

        return response()->success(
            PaymentResource::collection($payments)->response()->getData(true),
            'Payments retrieved successfully.'
        );
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
        try {
            $this->hubtelService->handleCallback($request->all());

            return response()->success(null, 'Callback processed successfully');
        } catch (\Exception $e) {
            return response()->error('Callback processing failed', 400);
        }
    }

    /**
     * Manually verify payment status
     */
    public function verifyPayment(Payment $payment): JsonResponse
    {
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
}
