<?php

namespace App\Console\Commands;

use App\Services\OTPService;
use Illuminate\Console\Command;

class CleanupExpiredOTPs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'otp:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup expired OTPs from the database';

    /**
     * Execute the console command.
     */
    public function handle(OTPService $otpService): int
    {
        $count = $otpService->cleanup();

        $this->info("Cleaned up {$count} expired OTP(s)");

        return self::SUCCESS;
    }
}
