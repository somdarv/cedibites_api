<?php

namespace App\Observers;

use App\Models\Employee;
use App\Models\Payment;
use App\Notifications\PaymentFailedNotification;

class PaymentObserver
{
    /**
     * Handle the Payment "updated" event.
     */
    public function updated(Payment $payment): void
    {
        // Only send notifications if payment status changed
        if (! $payment->wasChanged('payment_status')) {
            return;
        }

        // Notify customer when payment fails
        if ($payment->payment_status === 'failed') {
            $payment->customer?->user?->notify(new PaymentFailedNotification($payment));

            // Also notify branch manager
            $this->notifyBranchManager($payment);
        }
    }

    /**
     * Notify branch manager about payment issues.
     */
    protected function notifyBranchManager(Payment $payment): void
    {
        $manager = Employee::where('branch_id', $payment->order->branch_id)
            ->whereHas('user.roles', fn ($q) => $q->where('name', 'manager'))
            ->with('user')
            ->first();

        $manager?->user?->notify(new PaymentFailedNotification($payment));
    }
}
