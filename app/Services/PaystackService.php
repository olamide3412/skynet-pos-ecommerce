<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaystackService
{
    protected $secretKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
        $this->baseUrl = config('services.paystack.base_url', 'https://api.paystack.co');
    }

    /**
     * Verify whether the incoming Paystack webhook signature matches the computed HMAC-SHA512.
     */
    public function isValidWebhookSignature(?string $signature, string $payload): bool
    {
        if (empty($this->secretKey) || empty($signature)) {
            return false;
        }

        $computedSignature = hash_hmac('sha512', $payload, (string) $this->secretKey);

        return hash_equals($computedSignature, (string) $signature);
    }

    public function initializePayment($order, $email)
    {
        $reference = 'ps_' . uniqid() . '_' . $order->id;
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", [
                'email' => $email,
                'amount' => (int) round(floatval($order->total_amount) * 100), // integer amount in kobo
                'reference' => $reference,
                'callback_url' => route('payment.callback', ['provider' => 'paystack']),
            ]);

        $json = $response->json();
        if (is_array($json) && !empty($json['status'])) {
            $json['data']['reference'] = $json['data']['reference'] ?? $reference;
        }

        return $json;
    }

    public function verifyPayment($reference)
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        return $response->json();
    }
}
