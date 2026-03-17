@extends('emails.layout')

@section('title', 'Branch Manager Assignment - CediBites')

@section('content')
    <h2 class="greeting">Hello {{ $user->name }}</h2>

    <p class="message">
        Congratulations! You have been assigned as the manager of <strong>{{ $branch->name }}</strong> branch.
    </p>

    <div class="order-box">
        <h3>Branch Details</h3>
        <p style="margin: 0; color: #fbf6ed; font-family: 'Cabin', sans-serif; font-size: 16px; font-weight: 600;">
            {{ $branch->name }}
        </p>
        <p style="margin: 8px 0 0 0; font-size: 14px; color: #8b7f70; font-family: 'Cabin', sans-serif;">
            {{ $branch->address }}
        </p>
        @if($branch->phone)
            <p style="margin: 4px 0 0 0; font-size: 14px; color: #8b7f70; font-family: 'Cabin', sans-serif;">
                Phone: {{ $branch->phone }}
            </p>
        @endif
    </div>

    <p class="message">
        As a branch manager, you now have access to additional features and responsibilities including:
    </p>

    <ul style="color: #8b7f70; font-family: 'Cabin', sans-serif; font-size: 14px; line-height: 1.6; margin: 16px 0;">
        <li>Managing branch operations and staff</li>
        <li>Overseeing daily sales and inventory</li>
        <li>Handling customer service issues</li>
        <li>Accessing branch performance reports</li>
    </ul>

    <div style="text-align: center;">
        <a href="{{ config('app.frontend_url', '') }}/staff/dashboard" class="button">
            Access Staff Dashboard
        </a>
    </div>

    <p class="message" style="margin-top: 15px; font-size: 13px; color: #8b7f70;">
        If you have any questions about your new role, please contact your administrator.
    </p>
@endsection