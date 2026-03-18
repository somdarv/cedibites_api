@extends('emails.layout')

@section('title', 'Order Out for Delivery - CediBites')

@section('content')
    <h2 class="greeting">Hello {{ $order->customer->user->name }}</h2>
    
    <p class="message">
        Your order is on its way! Our delivery partner is heading to your location now.
    </p>
    
    <div class="order-box">
        <h3>Order Details</h3>
        
        <div class="order-detail">
            <span class="order-label">Order Number:</span>
            <span class="order-value">{{ $order->order_number }}</span>
        </div>
        
        <div class="order-detail">
            <span class="order-label">Total Amount:</span>
            <span class="order-value">GHS {{ number_format($order->total_amount, 2) }}</span>
        </div>
        
        <div class="order-detail">
            <span class="order-label">Delivery Address:</span>
            <span class="order-value">{{ $order->delivery_address }}</span>
        </div>
    </div>
    
    <div style="text-align: center;">
        <a href="{{ config('app.frontend_url') }}/orders/{{ $order->id }}" class="button">
            Track Delivery
        </a>
    </div>
    
    <p class="message" style="margin-top: 15px; font-size: 13px; color: #8b7f70;">
        Need help? Contact us at <a href="tel:+233XXXXXXXXX" style="color: #e49925;">+233 XX XXX XXXX</a> or reply to this email.
    </p>
@endsection
