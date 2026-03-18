<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [60, 300, 900];

    public $timeout = 30;

    public function __construct(
        public Payment $payment
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', SmsChannel::class];

        if ($notifiable->email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payment Failed for Order #{$this->payment->order->order_number}")
            ->view('emails.payments.failed', ['payment' => $this->payment]);
    }

    public function toSms(object $notifiable): string
    {
        return "CediBites: Payment failed for order #{$this->payment->order->order_number}. Please retry or contact support.";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'order_id' => $this->payment->order_id,
            'order_number' => $this->payment->order->order_number,
            'amount' => $this->payment->amount,
            'message' => "Payment failed for order #{$this->payment->order->order_number}. Please retry.",
        ];
    }
}
