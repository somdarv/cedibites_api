<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected string $secretKey;

    protected string $publicKey;

    protected string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
        $this->publicKey = config('services.paystack.public_key');
        $this->baseUrl = 'https://api.paystack.co';
    }

    /**
     * Initialize a transaction.
     */
    public function initializeTransaction(array $data): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", $data);

        if ($response->failed()) {
            Log::error('Paystack initialization failed', [
                'response' => $response->json(),
                'data' => $data,
            ]);

            throw new \Exception('Failed to initialize payment: '.$response->json()['message'] ?? 'Unknown error');
        }

        return $response->json();
    }

    /**
     * Verify a transaction.
     */
    public function verifyTransaction(string $reference): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        if ($response->failed()) {
            Log::error('Paystack verification failed', [
                'reference' => $reference,
                'response' => $response->json(),
            ]);

            throw new \Exception('Failed to verify payment: '.$response->json()['message'] ?? 'Unknown error');
        }

        return $response->json();
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $hash = hash_hmac('sha512', $payload, $this->secretKey);

        return hash_equals($hash, $signature);
    }

    /**
     * Get public key for frontend.
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }
}
