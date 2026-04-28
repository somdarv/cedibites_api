<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class SmartErrorService
{
    /**
     * Get business-friendly error feed with categories and severity.
     *
     * @return array<string, mixed>
     */
    public function getFeed(int $limit = 50): array
    {
        $errors = collect();

        $errors = $errors
            ->merge($this->loginFailures())
            ->merge($this->failedJobs())
            ->merge($this->paymentErrors())
            ->merge($this->recentExceptions())
            ->sortByDesc('timestamp')
            ->take($limit)
            ->values();

        return [
            'errors' => $errors->toArray(),
            'summary' => $this->summary($errors),
        ];
    }

    /**
     * Detect repeated login failures and report them as friendly messages.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function loginFailures(): Collection
    {
        $since = now()->subHours(24);

        // Get login failure activities in the last 24 hours
        $failures = Activity::where('log_name', 'auth')
            ->whereIn('event', ['login_failed', 'staff_login_failed'])
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        // Group by identifier (phone/email) to detect patterns
        $grouped = $failures->groupBy(fn ($a) => $a->properties['identifier'] ?? $a->properties['phone'] ?? $a->subject_id ?? 'unknown');

        $errors = collect();

        foreach ($grouped as $identifier => $attempts) {
            // Check for burst patterns (3+ failures in 5 minutes)
            $recentAttempts = $attempts->filter(fn ($a) => $a->created_at->gt(now()->subMinutes(5)));

            if ($recentAttempts->count() >= 3) {
                $userName = $attempts->first()->properties['name']
                    ?? $attempts->first()->properties['identifier']
                    ?? $identifier;
                $errors->push([
                    'id' => 'login-burst-'.$identifier,
                    'category' => 'authentication',
                    'severity' => 'warning',
                    'title' => "{$userName} failed to login {$recentAttempts->count()} times in the last 5 minutes",
                    'description' => 'Probably forgot their password. Consider resetting it for them.',
                    'phone' => $identifier,
                    'timestamp' => $recentAttempts->first()->created_at->toIso8601String(),
                    'count' => $recentAttempts->count(),
                    'action' => 'reset_password',
                ]);
            } elseif ($attempts->count() >= 5) {
                $userName = $attempts->first()->properties['name']
                    ?? $attempts->first()->properties['identifier']
                    ?? $identifier;
                $errors->push([
                    'id' => 'login-repeated-'.$identifier,
                    'category' => 'authentication',
                    'severity' => 'info',
                    'title' => "{$userName} has had {$attempts->count()} failed logins today",
                    'description' => 'Spread across the day — may be entering wrong credentials.',
                    'phone' => $identifier,
                    'timestamp' => $attempts->first()->created_at->toIso8601String(),
                    'count' => $attempts->count(),
                    'action' => 'review',
                ]);
            }
        }

        // Also report total login failures as a summary
        if ($failures->count() > 0) {
            $errors->push([
                'id' => 'login-summary-'.now()->format('Y-m-d'),
                'category' => 'authentication',
                'severity' => 'info',
                'title' => "{$failures->count()} failed login attempts in the last 24 hours",
                'description' => "Across {$grouped->count()} different accounts.",
                'timestamp' => $failures->first()->created_at->toIso8601String(),
                'count' => $failures->count(),
            ]);
        }

        return $errors;
    }

    /**
     * Get failed queue jobs translated into business language.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function failedJobs(): Collection
    {
        $jobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(30)
            ->get();

        return $jobs->map(function ($job) {
            $payload = json_decode($job->payload, true);
            $jobClass = $payload['displayName'] ?? 'Unknown Job';
            $shortName = class_basename($jobClass);

            $category = $this->categorizeJob($shortName);
            $description = $this->describeJobFailure($shortName, $job->exception);

            return [
                'id' => 'failed-job-'.$job->id,
                'category' => $category,
                'severity' => 'error',
                'title' => $description['title'],
                'description' => $description['detail'],
                'timestamp' => Carbon::parse($job->failed_at)->toIso8601String(),
                'job_id' => $job->id,
                'action' => 'retry_job',
            ];
        });
    }

    /**
     * Detect payment-related errors from activity logs.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function paymentErrors(): Collection
    {
        $since = now()->subHours(24);

        $errors = Activity::where('log_name', 'payment')
            ->whereIn('event', ['payment_failed', 'callback_error', 'rmp_failed'])
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return $errors->map(function ($activity) {
            $props = $activity->properties->toArray();
            $orderNumber = $props['order_number'] ?? 'unknown';
            $method = $props['payment_method'] ?? 'unknown';
            $reason = $props['error'] ?? $props['reason'] ?? 'Unknown error';

            $title = match ($activity->event) {
                'payment_failed' => "Payment failed for order #{$orderNumber}",
                'callback_error' => "Payment callback error on order #{$orderNumber}",
                'rmp_failed' => "MoMo payment prompt failed for order #{$orderNumber}",
                default => "Payment issue on order #{$orderNumber}",
            };

            return [
                'id' => 'payment-'.$activity->id,
                'category' => 'payments',
                'severity' => 'error',
                'title' => $title,
                'description' => "Method: {$method}. Reason: {$reason}",
                'timestamp' => $activity->created_at->toIso8601String(),
                'action' => 'view_order',
                'order_number' => $orderNumber,
            ];
        });
    }

    /**
     * Read recent Laravel log file for exceptions.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function recentExceptions(): Collection
    {
        $logPath = storage_path('logs/laravel.log');

        if (! file_exists($logPath)) {
            return collect();
        }

        // Read last 50KB of the log file
        $handle = fopen($logPath, 'r');
        if (! $handle) {
            return collect();
        }

        $fileSize = filesize($logPath);
        $readSize = min($fileSize, 50 * 1024);
        fseek($handle, -$readSize, SEEK_END);
        $content = fread($handle, $readSize);
        fclose($handle);

        if (! $content) {
            return collect();
        }

        // Parse log entries — match [YYYY-MM-DD HH:MM:SS] environment.ERROR:
        preg_match_all(
            '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(ERROR|CRITICAL|EMERGENCY): (.+?)(?=\n\[|\z)/s',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        $errors = collect();
        $cutoff = now()->subHours(24);

        foreach (array_slice($matches, -20) as $i => $match) {
            $timestamp = Carbon::parse($match[1]);
            if ($timestamp->lt($cutoff)) {
                continue;
            }

            $level = strtolower($match[2]);
            $message = trim(explode("\n", $match[3])[0]);
            $translated = $this->translateException($message);

            $errors->push([
                'id' => 'exception-'.$i.'-'.$timestamp->timestamp,
                'category' => $translated['category'],
                'severity' => $level === 'critical' || $level === 'emergency' ? 'critical' : 'error',
                'title' => $translated['title'],
                'description' => $translated['detail'],
                'timestamp' => $timestamp->toIso8601String(),
                'raw' => mb_substr($message, 0, 200),
            ]);
        }

        return $errors;
    }

    private function categorizeJob(string $shortName): string
    {
        return match (true) {
            str_contains($shortName, 'Payment'), str_contains($shortName, 'Hubtel') => 'payments',
            str_contains($shortName, 'Notification'), str_contains($shortName, 'Mail'), str_contains($shortName, 'Sms') => 'notifications',
            str_contains($shortName, 'Order') => 'orders',
            default => 'system',
        };
    }

    /**
     * @return array{title: string, detail: string}
     */
    private function describeJobFailure(string $shortName, string $exception): array
    {
        $firstLine = trim(explode("\n", $exception)[0]);

        return match (true) {
            str_contains($shortName, 'Payment') => [
                'title' => 'A payment processing job failed',
                'detail' => "The system couldn't complete a payment task. Error: ".mb_substr($firstLine, 0, 150),
            ],
            str_contains($shortName, 'Notification'), str_contains($shortName, 'Mail') => [
                'title' => 'A notification failed to send',
                'detail' => "An email or SMS notification couldn't be delivered. ".mb_substr($firstLine, 0, 150),
            ],
            str_contains($shortName, 'Sms') => [
                'title' => 'An SMS failed to send',
                'detail' => 'The SMS gateway rejected or timed out. '.mb_substr($firstLine, 0, 150),
            ],
            str_contains($shortName, 'Order') => [
                'title' => 'An order processing job failed',
                'detail' => 'A background order task encountered an error. '.mb_substr($firstLine, 0, 150),
            ],
            default => [
                'title' => "{$shortName} job failed",
                'detail' => mb_substr($firstLine, 0, 200),
            ],
        };
    }

    /**
     * Translate raw exception messages into business-friendly language.
     *
     * @return array{category: string, title: string, detail: string}
     */
    private function translateException(string $message): array
    {
        return match (true) {
            str_contains($message, 'SQLSTATE') => [
                'category' => 'database',
                'title' => 'Database query error detected',
                'detail' => 'A database operation failed — this may affect order processing or data retrieval.',
            ],
            str_contains($message, 'cURL error'), str_contains($message, 'ConnectionException') => [
                'category' => 'integrations',
                'title' => 'External service connection failed',
                'detail' => 'The system couldn\'t reach an external API (Hubtel, SMS gateway, etc.).',
            ],
            str_contains($message, 'HubtelSmsService'), str_contains($message, 'Invalid phone number format') => [
                'category' => 'integrations',
                'title' => 'SMS notification failed',
                'detail' => 'An SMS could not be sent — the customer\'s phone number may be in an invalid format.',
            ],
            str_contains($message, 'Hubtel'), str_contains($message, 'hubtel') => [
                'category' => 'payments',
                'title' => 'Hubtel payment gateway error',
                'detail' => 'A payment-related API call to Hubtel returned an error.',
            ],
            str_contains($message, 'Too Many Attempts'), str_contains($message, 'ThrottleRequests') => [
                'category' => 'security',
                'title' => 'Rate limit triggered',
                'detail' => 'Someone or something is making too many requests — could be a brute-force attempt.',
            ],
            str_contains($message, 'Unauthenticated'), str_contains($message, 'AuthenticationException') => [
                'category' => 'authentication',
                'title' => 'Unauthenticated access attempt',
                'detail' => 'A request was made to a protected route without valid credentials.',
            ],
            str_contains($message, 'NotFoundHttpException'), str_contains($message, '404') => [
                'category' => 'system',
                'title' => 'Page or API route not found',
                'detail' => 'A request was made to a URL that doesn\'t exist — may be a broken link or a bot.',
            ],
            str_contains($message, 'MethodNotAllowedHttpException') => [
                'category' => 'system',
                'title' => 'Wrong HTTP method used',
                'detail' => 'A request used GET instead of POST (or vice versa) — likely a frontend bug.',
            ],
            str_contains($message, 'ValidationException') => [
                'category' => 'system',
                'title' => 'Data validation failed',
                'detail' => 'A form or API request sent invalid data that didn\'t pass validation rules.',
            ],
            default => [
                'category' => 'system',
                'title' => 'Application error detected',
                'detail' => mb_substr($message, 0, 200),
            ],
        };
    }

    /**
     * @return array<string, int>
     */
    private function summary(Collection $errors): array
    {
        return [
            'total' => $errors->count(),
            'critical' => $errors->where('severity', 'critical')->count(),
            'errors' => $errors->where('severity', 'error')->count(),
            'warnings' => $errors->where('severity', 'warning')->count(),
            'info' => $errors->where('severity', 'info')->count(),
            'by_category' => $errors->countBy('category')->toArray(),
        ];
    }
}
