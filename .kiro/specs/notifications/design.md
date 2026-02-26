# Notification System - Design Document

## Architecture Overview

This notification system leverages Laravel 12's built-in notification infrastructure with custom SMS channel integration. The design follows Laravel conventions and integrates seamlessly with the existing SMSService.

### Core Components

1. **Notification Classes** - Individual notification types extending Laravel's base Notification class
2. **Custom SMS Channel** - Bridge between Laravel notifications and existing SMSService
3. **Notification Preferences** - User-configurable notification settings
4. **API Layer** - RESTful endpoints for notification management
5. **Event Listeners** - Automatic notification triggers based on model events

---

## Database Schema

### notifications table (Laravel default)

```php
Schema::create('notifications', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('type');
    $table->morphs('notifiable');
    $table->text('data');
    $table->timestamp('read_at')->nullable();
    $table->timestamps();
    
    $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
});
```

### notification_preferences table

Not needed for MVP. All notifications are sent via all available channels (database, email if exists, SMS).

---

## Notification Classes

### Base Structure

All notification classes follow this pattern:

```php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Channels\SmsChannel;

class OrderConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    
    public function __construct(
        public Order $order
    ) {}
    
    public function via(object $notifiable): array
    {
        $channels = ['database', SmsChannel::class];
        
        // Add email if user has email address
        if ($notifiable->email) {
            $channels[] = 'mail';
        }
        
        return $channels;
    }
    
    public function toDatabase(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'total_amount' => $this->order->total_amount,
            'estimated_time' => $this->order->estimated_prep_time,
            'message' => "Your order #{$this->order->order_number} has been confirmed!",
        ];
    }
    
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order #{$this->order->order_number} Confirmed")
            ->view('emails.orders.confirmed', ['order' => $this->order]);
    }
    
    public function toSms(object $notifiable): string
    {
        return "CediBites: Order #{$this->order->order_number} confirmed! " .
               "Total: GHS {$this->order->total_amount}. " .
               "Estimated time: {$this->order->estimated_prep_time} mins.";
    }
}
```

### Customer Notification Classes

#### 1. OrderConfirmedNotification
- **Trigger:** Payment successful, order created
- **Channels:** Database, Email (if available), SMS
- **Data:** order_id, order_number, total_amount, estimated_time
- **SMS:** "Order #ORD001 confirmed! Total: GHS 45.50. Estimated time: 30 mins."
- **Email:** Order confirmation with details, items, and tracking link

#### 2. OrderPreparingNotification
- **Trigger:** Order status → 'preparing'
- **Channels:** Database, Email (if available), SMS
- **Data:** order_id, order_number, status
- **SMS:** "Your order #ORD001 is now being prepared!"
- **Email:** Order preparation update with estimated completion time

#### 3. OrderReadyNotification
- **Trigger:** Order status → 'ready' or 'ready_for_pickup'
- **Channels:** Database, Email (if available), SMS
- **Data:** order_id, order_number, order_type, branch_info
- **SMS:** "Order #ORD001 is ready for pickup at [Branch Name]!"
- **Email:** Order ready notification with pickup/delivery instructions

#### 4. OrderOutForDeliveryNotification
- **Trigger:** Order status → 'out_for_delivery'
- **Channels:** Database, Email (if available), SMS
- **Data:** order_id, order_number, estimated_delivery_time
- **SMS:** "Your order #ORD001 is out for delivery! ETA: 15 mins."
- **Email:** Delivery notification with tracking and ETA

#### 5. OrderCompletedNotification
- **Trigger:** Order status → 'completed' or 'delivered'
- **Channels:** Database, Email (if available), SMS
- **Data:** order_id, order_number
- **SMS:** "Order #ORD001 completed! Thank you for choosing CediBites!"
- **Email:** Order completion with feedback request and receipt

#### 6. OrderCancelledNotification
- **Trigger:** Order status → 'cancelled'
- **Channels:** Database, Email (if available), SMS
- **Data:** order_id, order_number, cancelled_reason
- **SMS:** "Order #ORD001 has been cancelled. Reason: [reason]. Refund processing."
- **Email:** Cancellation details with refund information

#### 7. PaymentFailedNotification
- **Trigger:** Payment processing fails
- **Channels:** Database, Email (if available), SMS
- **Data:** order_id, order_number, failure_reason
- **SMS:** "Payment failed for order #ORD001. Please retry or contact support."
- **Email:** Payment failure details with retry instructions

### Employee Notification Classes

#### 1. NewOrderNotification
- **Trigger:** New order placed at branch
- **Data:** order_id, order_number, items_count, customer_name, special_instructions
- **Channels:** Database only (no SMS)
- **Recipients:** All active employees at the branch

#### 2. OrderCancellationNotification
- **Trigger:** Order cancelled
- **Data:** order_id, order_number, cancelled_reason
- **Channels:** Database only
- **Recipients:** Assigned employee

### Manager Notification Classes

#### 1. HighValueOrderNotification
- **Trigger:** Order total > GHS 200
- **Data:** order_id, order_number, total_amount, customer_info
- **Channels:** Database, SMS (optional)
- **Recipients:** Branch manager

#### 2. PaymentIssueNotification
- **Trigger:** Payment fails or disputed
- **Data:** order_id, order_number, issue_details, required_action
- **Channels:** Database, SMS
- **Recipients:** Branch manager

---

## Email Template Design

### Brand Colors & Assets

**Primary Colors:**
- Primary: `#e49925` (Orange)
- Primary Hover: `#f1ab3e`
- Secondary: `#6c833f` (Green)
- Neutral Light: `#fbf6ed`
- Brand Dark: `#1d1a16`
- Brand Darker: `#120f0d`

**Assets:**
- Logo: `/public/cblogo.webp` (needs to be copied to Laravel public folder)
- Font: Cabin (fallback to system fonts for email compatibility)

**Social Media:**
- Instagram: `#` (update with actual URL)
- Facebook: `#` (update with actual URL)
- WhatsApp: `#` (update with actual URL)

### Base Email Layout Template

Create `resources/views/emails/layout.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@yield('title', 'CediBites')</title>
    <style>
        /* Reset styles */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        
        /* Base styles */
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            font-family: 'Cabin', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background-color: #fbf6ed;
            color: #242424;
            line-height: 1.6;
        }
        
        /* Container */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        
        /* Header */
        .email-header {
            background-color: #1d1a16;
            padding: 30px 20px;
            text-align: center;
        }
        
        .logo {
            width: 60px;
            height: 60px;
        }
        
        .brand-name {
            color: #e49925;
            font-size: 28px;
            font-weight: bold;
            margin: 10px 0 0 0;
            font-family: 'Caprasimo', cursive;
        }
        
        /* Content */
        .email-content {
            padding: 40px 30px;
            background-color: #ffffff;
        }
        
        .greeting {
            font-size: 24px;
            font-weight: 600;
            color: #1d1a16;
            margin: 0 0 20px 0;
        }
        
        .message {
            font-size: 16px;
            color: #242424;
            margin: 0 0 20px 0;
        }
        
        /* Button */
        .button {
            display: inline-block;
            padding: 14px 32px;
            background-color: #e49925;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
            transition: background-color 0.2s;
        }
        
        .button:hover {
            background-color: #f1ab3e;
        }
        
        /* Order details box */
        .order-box {
            background-color: #fbf6ed;
            border-left: 4px solid #e49925;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .order-box h3 {
            margin: 0 0 15px 0;
            color: #1d1a16;
            font-size: 18px;
        }
        
        .order-detail {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0d5c4;
        }
        
        .order-detail:last-child {
            border-bottom: none;
        }
        
        .order-label {
            color: #8b7f70;
            font-weight: 500;
        }
        
        .order-value {
            color: #1d1a16;
            font-weight: 600;
        }
        
        /* Footer */
        .email-footer {
            background-color: #120f0d;
            padding: 30px 20px;
            text-align: center;
            color: #fbf6ed;
        }
        
        .social-links {
            margin: 20px 0;
        }
        
        .social-link {
            display: inline-block;
            margin: 0 10px;
            width: 36px;
            height: 36px;
            background-color: #e49925;
            border-radius: 50%;
            text-decoration: none;
            line-height: 36px;
            transition: background-color 0.2s;
        }
        
        .social-link:hover {
            background-color: #f1ab3e;
        }
        
        .social-icon {
            width: 20px;
            height: 20px;
            vertical-align: middle;
            filter: brightness(0) invert(1);
        }
        
        .footer-text {
            font-size: 14px;
            color: #8b7f70;
            margin: 10px 0;
        }
        
        .footer-links {
            margin: 15px 0;
        }
        
        .footer-link {
            color: #e49925;
            text-decoration: none;
            margin: 0 10px;
            font-size: 14px;
        }
        
        .footer-link:hover {
            color: #f1ab3e;
        }
        
        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-content {
                padding: 30px 20px !important;
            }
            
            .greeting {
                font-size: 20px !important;
            }
            
            .message {
                font-size: 14px !important;
            }
            
            .button {
                padding: 12px 24px !important;
                font-size: 14px !important;
            }
        }
    </style>
</head>
<body>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #fbf6ed;">
        <tr>
            <td style="padding: 20px 0;">
                <table role="presentation" class="email-container" cellspacing="0" cellpadding="0" border="0" width="600" align="center">
                    
                    <!-- Header -->
                    <tr>
                        <td class="email-header">
                            <img src="{{ asset('images/cblogo.webp') }}" alt="CediBites Logo" class="logo">
                            <h1 class="brand-name">CediBites</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td class="email-content">
                            @yield('content')
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td class="email-footer">
                            <div class="social-links">
                                <a href="https://instagram.com/cedibites" class="social-link" title="Instagram">
                                    <svg class="social-icon" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                    </svg>
                                </a>
                                <a href="https://facebook.com/cedibites" class="social-link" title="Facebook">
                                    <svg class="social-icon" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                    </svg>
                                </a>
                                <a href="https://wa.me/233XXXXXXXXX" class="social-link" title="WhatsApp">
                                    <svg class="social-icon" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                    </svg>
                                </a>
                            </div>
                            
                            <p class="footer-text">
                                &copy; {{ date('Y') }} CediBites. All rights reserved.
                            </p>
                            
                            <div class="footer-links">
                                <a href="{{ config('app.url') }}/privacy" class="footer-link">Privacy Policy</a>
                                <a href="{{ config('app.url') }}/terms" class="footer-link">Terms of Service</a>
                                <a href="{{ config('app.url') }}/contact" class="footer-link">Contact Us</a>
                            </div>
                            
                            <p class="footer-text" style="font-size: 12px; margin-top: 20px;">
                                You're receiving this email because you have an account with CediBites.<br>
                                If you have any questions, please contact us at <a href="mailto:support@cedibites.com" style="color: #e49925;">support@cedibites.com</a>
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
```

### Order Confirmation Email Template

Create `resources/views/emails/orders/confirmed.blade.php`:

```blade
@extends('emails.layout')

@section('title', 'Order Confirmed - CediBites')

@section('content')
    <h2 class="greeting">Hello {{ $order->customer->user->name }}! 👋</h2>
    
    <p class="message">
        Great news! Your order has been confirmed and is being processed. We'll notify you when it's ready.
    </p>
    
    <div class="order-box">
        <h3>Order Details</h3>
        
        <div class="order-detail">
            <span class="order-label">Order Number:</span>
            <span class="order-value">{{ $order->order_number }}</span>
        </div>
        
        <div class="order-detail">
            <span class="order-label">Order Type:</span>
            <span class="order-value">{{ ucfirst($order->order_type) }}</span>
        </div>
        
        <div class="order-detail">
            <span class="order-label">Total Amount:</span>
            <span class="order-value">GHS {{ number_format($order->total_amount, 2) }}</span>
        </div>
        
        <div class="order-detail">
            <span class="order-label">Estimated Time:</span>
            <span class="order-value">{{ $order->estimated_prep_time }} minutes</span>
        </div>
        
        @if($order->order_type === 'delivery')
        <div class="order-detail">
            <span class="order-label">Delivery Address:</span>
            <span class="order-value">{{ $order->delivery_address }}</span>
        </div>
        @endif
    </div>
    
    <div style="text-align: center;">
        <a href="{{ config('app.frontend_url') }}/orders/{{ $order->id }}" class="button">
            View Order Details
        </a>
    </div>
    
    <p class="message" style="margin-top: 30px; font-size: 14px; color: #8b7f70;">
        Need help? Contact us at <a href="tel:+233XXXXXXXXX" style="color: #e49925;">+233 XX XXX XXXX</a> or reply to this email.
    </p>
@endsection
```

### Additional Email Templates

Create similar templates for other notifications:

1. `resources/views/emails/orders/preparing.blade.php`
2. `resources/views/emails/orders/ready.blade.php`
3. `resources/views/emails/orders/out-for-delivery.blade.php`
4. `resources/views/emails/orders/completed.blade.php`
5. `resources/views/emails/orders/cancelled.blade.php`
6. `resources/views/emails/payments/failed.blade.php`

### Assets Setup

1. Copy logo from frontend to Laravel:
```bash
cp cedibites/public/cblogo.webp cedibites_api/public/images/cblogo.webp
```

2. Update `.env` with frontend URL:
```env
FRONTEND_URL=http://localhost:3000
```

3. Update `config/app.php`:
```php
'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
```

---

## Custom SMS Channel

### SmsChannel Class

```php
namespace App\Channels;

use App\Services\SMSService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SmsChannel
{
    public function __construct(
        protected SMSService $smsService
    ) {}
    
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toSms')) {
            return;
        }
        
        $phone = $notifiable->phone ?? $notifiable->customer?->phone;
        
        if (!$phone) {
            Log::warning('Cannot send SMS notification: no phone number', [
                'notifiable_id' => $notifiable->id,
                'notification' => get_class($notification),
            ]);
            return;
        }
        
        $message = $notification->toSms($notifiable);
        
        try {
            $this->smsService->send($phone, $message);
            
            Log::info('SMS notification sent', [
                'notifiable_id' => $notifiable->id,
                'phone' => $phone,
                'notification' => get_class($notification),
            ]);
        } catch (\Exception $e) {
            Log::error('SMS notification failed', [
                'notifiable_id' => $notifiable->id,
                'phone' => $phone,
                'notification' => get_class($notification),
                'error' => $e->getMessage(),
            ]);
            
            throw $e; // Re-throw to trigger queue retry
        }
    }
}
```

### Channel Registration

Register in `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Notification;
use App\Channels\SmsChannel;

public function boot(): void
{
    Notification::resolved(function ($service) {
        $service->extend('sms', function ($app) {
            return new SmsChannel($app->make(SMSService::class));
        });
    });
}
```

---

## Models

No additional models needed. User model already has `Notifiable` trait from Laravel.

---

## Event Listeners

### Order Status Change Observer

```php
namespace App\Observers;

use App\Models\Order;
use App\Notifications\OrderConfirmedNotification;
use App\Notifications\OrderPreparingNotification;
use App\Notifications\OrderReadyNotification;
use App\Notifications\OrderOutForDeliveryNotification;
use App\Notifications\OrderCompletedNotification;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\NewOrderNotification;
use App\Notifications\OrderCancellationNotification;
use App\Models\Employee;

class OrderObserver
{
    public function created(Order $order): void
    {
        // Notify customer
        $order->customer?->user?->notify(new OrderConfirmedNotification($order));
        
        // Notify employees at branch
        $this->notifyBranchEmployees($order, new NewOrderNotification($order));
    }
    
    public function updated(Order $order): void
    {
        if (!$order->wasChanged('status')) {
            return;
        }
        
        $customer = $order->customer?->user;
        
        match ($order->status) {
            'preparing' => $customer?->notify(new OrderPreparingNotification($order)),
            'ready', 'ready_for_pickup' => $customer?->notify(new OrderReadyNotification($order)),
            'out_for_delivery' => $customer?->notify(new OrderOutForDeliveryNotification($order)),
            'completed', 'delivered' => $customer?->notify(new OrderCompletedNotification($order)),
            'cancelled' => $this->handleCancellation($order),
            default => null,
        };
        
        // High value order notification for managers
        if ($order->wasChanged('status') && $order->status === 'received' && $order->total_amount > 200) {
            $this->notifyBranchManager($order);
        }
    }
    
    protected function handleCancellation(Order $order): void
    {
        // Notify customer
        $order->customer?->user?->notify(new OrderCancelledNotification($order));
        
        // Notify assigned employee
        $order->assignedEmployee?->user?->notify(new OrderCancellationNotification($order));
    }
    
    protected function notifyBranchEmployees(Order $order, $notification): void
    {
        $employees = Employee::where('branch_id', $order->branch_id)
            ->where('status', 'active')
            ->with('user')
            ->get();
        
        foreach ($employees as $employee) {
            $employee->user?->notify($notification);
        }
    }
    
    protected function notifyBranchManager(Order $order): void
    {
        $manager = Employee::where('branch_id', $order->branch_id)
            ->whereHas('user.roles', fn($q) => $q->where('name', 'manager'))
            ->with('user')
            ->first();
        
        $manager?->user?->notify(new HighValueOrderNotification($order));
    }
}
```

### Observer Registration

Register in `AppServiceProvider`:

```php
use App\Models\Order;
use App\Observers\OrderObserver;

public function boot(): void
{
    Order::observe(OrderObserver::class);
}
```

---

## API Layer

### NotificationController

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * Get user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $unreadOnly = $request->boolean('unread_only', false);
        
        $query = $request->user()->notifications();
        
        if ($unreadOnly) {
            $query->whereNull('read_at');
        }
        
        $notifications = $query->latest()->paginate($perPage);
        
        return response()->success($notifications);
    }
    
    /**
     * Get unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();
        
        return response()->success(['count' => $count]);
    }
    
    /**
     * Mark notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);
        
        $notification->markAsRead();
        
        return response()->success($notification);
    }
    
    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();
        
        return response()->success(['message' => 'All notifications marked as read']);
    }
    
    /**
     * Delete notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);
        
        $notification->delete();
        
        return response()->noContent();
    }
}
```



### API Routes

```php
// Notifications
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
});
```

---

## Queue Configuration

### Queue Setup

Notifications implement `ShouldQueue` for async delivery:

```php
class OrderConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    
    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min
    public $timeout = 30;
}
```

### Queue Worker

Run queue worker:
```bash
php artisan queue:work --queue=default,notifications
```

For production, use Supervisor or Laravel Horizon.

---

## Testing Strategy

### Unit Tests

#### Test Notification Classes

```php
test('OrderConfirmedNotification sends to all channels when email exists', function () {
    $user = User::factory()->create(['email' => 'customer@example.com']);
    $order = Order::factory()->create();
    
    $notification = new OrderConfirmedNotification($order);
    $channels = $notification->via($user);
    
    expect($channels)->toContain('database', 'mail', SmsChannel::class);
});

test('OrderConfirmedNotification skips email when user has no email', function () {
    $user = User::factory()->create(['email' => null]);
    $order = Order::factory()->create();
    
    $notification = new OrderConfirmedNotification($order);
    $channels = $notification->via($user);
    
    expect($channels)->toContain('database', SmsChannel::class)
        ->not->toContain('mail');
});


```

#### Test SMS Channel

```php
test('SmsChannel sends SMS via SMSService', function () {
    $smsService = Mockery::mock(SMSService::class);
    $smsService->shouldReceive('send')
        ->once()
        ->with('0241234567', Mockery::type('string'))
        ->andReturn(true);
    
    $channel = new SmsChannel($smsService);
    $user = User::factory()->create(['phone' => '0241234567']);
    $notification = new OrderConfirmedNotification(Order::factory()->create());
    
    $channel->send($user, $notification);
});
```

### Feature Tests

#### Test Notification API

```php
test('user can retrieve their notifications', function () {
    $user = User::factory()->create();
    $user->notify(new OrderConfirmedNotification(Order::factory()->create()));
    
    $response = $this->actingAs($user)->getJson('/api/v1/notifications');
    
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'type', 'data', 'read_at', 'created_at'],
            ],
        ]);
});

test('user can mark notification as read', function () {
    $user = User::factory()->create();
    $user->notify(new OrderConfirmedNotification(Order::factory()->create()));
    $notification = $user->notifications()->first();
    
    $response = $this->actingAs($user)
        ->patchJson("/api/v1/notifications/{$notification->id}/read");
    
    $response->assertOk();
    expect($notification->fresh()->read_at)->not->toBeNull();
});
```

#### Test Order Observer

```php
test('customer receives notification when order is created', function () {
    Notification::fake();
    
    $customer = Customer::factory()->create();
    $order = Order::factory()->create(['customer_id' => $customer->id]);
    
    Notification::assertSentTo(
        $customer->user,
        OrderConfirmedNotification::class
    );
});

test('employees receive notification for new orders', function () {
    Notification::fake();
    
    $branch = Branch::factory()->create();
    $employees = Employee::factory()->count(3)->create([
        'branch_id' => $branch->id,
        'status' => 'active',
    ]);
    
    $order = Order::factory()->create(['branch_id' => $branch->id]);
    
    foreach ($employees as $employee) {
        Notification::assertSentTo(
            $employee->user,
            NewOrderNotification::class
        );
    }
});
```

---

## Implementation Checklist

### Phase 1: Foundation
- [ ] Create notifications table migration (already done)
- [ ] Run migrations
- [ ] Copy logo to Laravel public folder
- [ ] Create base email layout template
- [ ] Create SmsChannel class
- [ ] Register SmsChannel in AppServiceProvider
- [ ] Add FRONTEND_URL to .env and config

### Phase 2: Customer Notifications
- [ ] Create OrderConfirmedNotification
- [ ] Create OrderPreparingNotification
- [ ] Create OrderReadyNotification
- [ ] Create OrderOutForDeliveryNotification
- [ ] Create OrderCompletedNotification
- [ ] Create OrderCancelledNotification
- [ ] Create PaymentFailedNotification
- [ ] Write unit tests for each notification

### Phase 3: Employee Notifications
- [ ] Create NewOrderNotification
- [ ] Create OrderCancellationNotification
- [ ] Create HighValueOrderNotification
- [ ] Create PaymentIssueNotification
- [ ] Write unit tests for employee notifications

### Phase 4: Event Integration
- [ ] Create OrderObserver
- [ ] Register OrderObserver
- [ ] Test order creation triggers notifications
- [ ] Test order status changes trigger notifications
- [ ] Test employee notifications

### Phase 5: API Layer
- [ ] Create NotificationController
- [ ] Add API routes
- [ ] Write feature tests for API endpoints

### Phase 6: Testing & Polish
- [ ] Run all tests
- [ ] Test SMS delivery in staging
- [ ] Test notification preferences
- [ ] Test queue processing
- [ ] Add logging and monitoring
- [ ] Update API documentation

---

## Correctness Properties

### Property 1: Notification Delivery Guarantee
**Statement:** Every order status change that requires customer notification MUST result in a notification being queued.

**Test Strategy:**
```php
// Property-based test
test('all order status changes trigger appropriate notifications', function () {
    $statusTransitions = [
        'preparing' => OrderPreparingNotification::class,
        'ready' => OrderReadyNotification::class,
        'out_for_delivery' => OrderOutForDeliveryNotification::class,
        'completed' => OrderCompletedNotification::class,
        'cancelled' => OrderCancelledNotification::class,
    ];
    
    foreach ($statusTransitions as $status => $notificationClass) {
        Notification::fake();
        
        $order = Order::factory()->create(['status' => 'received']);
        $order->update(['status' => $status]);
        
        Notification::assertSentTo(
            $order->customer->user,
            $notificationClass
        );
    }
});
```



### Property 3: Idempotency
**Statement:** Sending the same notification multiple times MUST NOT result in duplicate SMS (database notifications can be duplicated).

**Test Strategy:**
```php
test('SMS notifications are idempotent within time window', function () {
    $smsService = Mockery::spy(SMSService::class);
    app()->instance(SMSService::class, $smsService);
    
    $user = User::factory()->create();
    $order = Order::factory()->create();
    $notification = new OrderConfirmedNotification($order);
    
    // Send twice quickly
    $user->notify($notification);
    $user->notify($notification);
    
    // Should only send SMS once (implement deduplication logic)
    $smsService->shouldHaveReceived('send')->once();
});
```

### Property 4: Data Consistency
**Statement:** Notification data stored in database MUST match the order state at notification time.

**Test Strategy:**
```php
test('notification data matches order state', function () {
    $order = Order::factory()->create([
        'order_number' => 'ORD001',
        'total_amount' => 45.50,
    ]);
    
    $order->customer->user->notify(new OrderConfirmedNotification($order));
    
    $notification = $order->customer->user->notifications()->first();
    
    expect($notification->data['order_number'])->toBe('ORD001')
        ->and($notification->data['total_amount'])->toBe(45.50);
});
```

---

## Security Considerations

### Rate Limiting
- Limit notifications per user: max 10/hour
- Prevent notification spam attacks
- Log excessive notification attempts

### Data Privacy
- No sensitive data in SMS (passwords, full card numbers)
- Notification data encrypted at rest
- Users can delete notification history
- Comply with data protection regulations

### Authorization
- Users can only access their own notifications
- Employees can only see branch-related notifications
- Managers have elevated notification access

---

## Performance Considerations

### Queue Processing
- Use dedicated queue for notifications
- Process notifications asynchronously
- Retry failed notifications with exponential backoff
- Monitor queue depth and processing time

### Database Optimization
- Index on (notifiable_type, notifiable_id, read_at)
- Index on created_at for pagination
- Consider archiving old notifications (>90 days)
- Use database query optimization for large datasets

### SMS Delivery
- Batch SMS when possible (provider dependent)
- Use SMS provider's async API
- Implement circuit breaker for SMS failures
- Monitor SMS delivery rates

---

## Monitoring & Logging

### Metrics to Track
- Notifications sent per hour/day
- SMS delivery success rate
- Notification processing time
- Queue depth and lag
- Failed notification rate
- User opt-out rate

### Logging
- Log all notification sends (info level)
- Log SMS delivery failures (error level)
- Log preference changes (info level)
- Log rate limit violations (warning level)

### Alerts
- Alert on SMS delivery rate < 90%
- Alert on queue depth > 1000
- Alert on notification processing time > 60s
- Alert on failed notification rate > 10%

---

## Future Enhancements

### Phase 2 Features
- Email notifications
- Push notifications (mobile app)
- WhatsApp notifications
- Multi-language support
- Notification templates management UI
- A/B testing for notification content
- Notification scheduling
- Rich notifications with images/buttons

### Analytics
- Notification open rates
- Notification action rates
- User engagement metrics
- Notification effectiveness analysis

---

## Migration Plan

### Step 1: Infrastructure Setup
1. Run migrations
2. Deploy SmsChannel
3. Deploy NotificationPreference model
4. Create default preferences for existing users

### Step 2: Gradual Rollout
1. Deploy notification classes (disabled)
2. Test in staging environment
3. Enable for 10% of users
4. Monitor metrics
5. Gradually increase to 100%

### Step 3: Legacy System Deprecation
1. Keep existing SMS for OTP
2. Migrate order notifications to new system
3. Monitor for issues
4. Deprecate direct SMSService calls (except OTP)

---

## Documentation

### Developer Docs
- How to create new notification types
- How to test notifications locally
- How to add new notification channels
- Troubleshooting guide

### API Docs
- Notification endpoints
- Request/response formats
- Error codes
- Rate limits
- Example requests

### User Docs
- How to manage notification preferences
- What notifications are sent when
- How to opt-out
- Troubleshooting notification issues

---

## Dependencies

- Laravel 12 (installed)
- Laravel Sanctum (installed)
- Existing SMSService
- Queue system (database or Redis)
- Existing User, Order, Customer, Employee models

---

## Estimated Effort

- Phase 1 (Foundation): 3 hours
- Phase 2 (Customer Notifications): 8 hours
- Phase 3 (Employee Notifications): 4 hours
- Phase 4 (Event Integration): 3 hours
- Phase 5 (API Layer): 3 hours
- Phase 6 (Testing & Polish): 4 hours

**Total: ~25 hours** (3 days)

---

## Success Criteria

- [ ] All notification types implemented and tested
- [ ] SMS delivery rate > 95%
- [ ] All API endpoints functional
- [ ] Notification preferences working
- [ ] All tests passing (unit + feature)
- [ ] Documentation complete
- [ ] Deployed to staging and tested
- [ ] Performance metrics within acceptable range
- [ ] No critical bugs in production for 1 week
