<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
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

        return response()->success(
            new PaymentResource($payment->fresh(['order.customer.user'])),
            'Payment refunded successfully.'
        );
    }
}
