@extends('emails.layout')

@section('title', 'Branch Manager Role Update - CediBites')

@section('content')
    <h2 class="greeting">Hello {{ $user->name }}</h2>

    <p class="message">
        We're writing to inform you that your role as manager of <strong>{{ $branch->name }}</strong> branch has been updated.
    </p>

    <div class="order-box">
        <h3>Branch Details</h3>
        <p style="margin: 0; color: #fbf6ed; font-family: 'Cabin', sans-serif; font-size: 16px; font-weight: 600;">
            {{ $branch->name }}
        </p>
        <p style="margin: 8px 0 0 0; font-size: 14px; color: #8b7f70; font-family: 'Cabin', sans-serif;">
            {{ $branch->address }}
        </p>
    </div>

    <p class="message">
        You will no longer have manager-level access for this branch, but you may still have access to other areas of the system based on your current role and permissions.
    </p>

    <div style="text-align: center;">
        <a href="{{ config('app.frontend_url', '') }}/staff/dashboard" class="button">
            Access Staff Dashboard
        </a>
    </div>

    <p class="message" style="margin-top: 15px; font-size: 13px; color: #8b7f70;">
        If you have any questions about this change, please contact your administrator.
    </p>
@endsection