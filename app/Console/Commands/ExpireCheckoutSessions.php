<?php

namespace App\Console\Commands;

use App\Models\CheckoutSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireCheckoutSessions extends Command
{
    protected $signature = 'checkout-sessions:expire';

    protected $description = 'Mark expired checkout sessions and broadcast updates';

    public function handle(): int
    {
        $expired = CheckoutSession::whereIn('status', ['pending', 'payment_initiated'])
            ->where('is_manual_entry', false)
            ->where('expires_at', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($expired as $session) {
            $session->update(['status' => 'expired']);
            $count++;
        }

        Log::info("Expired {$count} checkout sessions.");
        $this->info("Expired {$count} checkout sessions.");

        return self::SUCCESS;
    }
}
