@extends('emails.layout')

@section('title', 'Welcome to CediBites')

@section('content')
    <h2 class="greeting">Welcome to CediBites, {{ $user->name }}</h2>
    
    <p class="message">
        We're thrilled to have you join our community! CediBites makes it easy to order delicious meals from your favorite local spots.
    </p>
    
    <div class="order-box">
        <h3>What You Can Do</h3>
        
        <p style="margin: 8px 0; color: #fbf6ed; font-family: 'Cabin', sans-serif; font-size: 14px; line-height: 1.8;">
            ✓ Browse our menu of delicious meals<br>
            ✓ Order for delivery or pickup<br>
            ✓ Track your orders in real-time<br>
            ✓ Save your favorite items<br>
            ✓ Get exclusive deals and offers
        </p>
    </div>
    
    <div style="text-align: center;">
        <a href="{{ config('app.frontend_url') }}/menu" class="button">
            Start Ordering
        </a>
    </div>
    
    <p class="message" style="margin-top: 15px; font-size: 13px; color: #8b7f70;">
        Need help getting started? Contact us at <a href="tel:+233XXXXXXXXX" style="color: #e49925;">+233 XX XXX XXXX</a> or reply to this email.
    </p>
@endsection
