@extends('emails.layout')

@section('title', 'Your verification code')

@section('content')
    <h2 class="greeting">Your CediBites verification code</h2>

    <p class="message">
        Use the code below to verify your phone number and sign in to CediBites.
    </p>

    <div class="order-box" style="text-align: center; padding: 24px;">
        <span style="font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #e49925; font-family: monospace;">{{ $otp }}</span>
    </div>

    <p class="message" style="font-size: 13px; color: #8b7f70;">
        This code is valid for 5 minutes. If you didn't request this code, you can safely ignore this email.
    </p>
@endsection
