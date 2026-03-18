@extends('emails.layout')

@section('title', 'Payment Failed - CediBites')

@section('content')
    <h2 class="greeting">Payment Failed</h2>
    
    <p class="message">
        We were unable to process your payment for order #{{ $payment->order->order_number }}.
    </p>
    
    <div class="order-box">
        <h3>Payment Details</h3>
        
        <div class="order-detail">
            <span class="order-label">Order Number:</span>
            <span class="order-value">{{ $payment->order->order_number }}</span>
        </div>
        
        <div class="order-detail">
            <span class="order-label">Amount:</span>
            <span class="order-value">GHS {{ number_format($payment->amount, 2) }}</span>
        </div>
        
        <div class="order-detail">
            <span class="order-label">Payment Method:</span>
            <span class="order-value">{{ ucfirst($payment->payment_method) }}</span>
        </div>
    </div>
    
    <p class="message">
        Please try again or use a different payment method. If the problem persists, contact your bank or our support team.
    </p>
    
    <div style="text-align: center;">
        <a href="{{ config('app.frontend_url') }}/orders/{{ $payment->order_id }}" class="button">
            Retry Payment
        </a>
    </div>
    
    <p class="message" style="margin-top: 15px; font-size: 13px; color: #8b7f70;">
        Need help? Contact us at <a href="tel:+233XXXXXXXXX" style="color: #e49925;">+233 XX XXX XXXX</a> or reply to this email.
    </p>
@endsection
