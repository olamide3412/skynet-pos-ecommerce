<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected $secretKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->secretKey = trim((string) config('services.paystack.secret_key'));
        $this->baseUrl = rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');
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
        $amountKobo = (int) round(floatval($order->total_amount) * 100);
        $callbackUrl = route('payment.callback', ['provider' => 'paystack']);
        $keyPrefix = $this->secretKey !== '' ? substr($this->secretKey, 0, 8) : '(empty)';

        try {
            $response = Http::withToken($this->secretKey)
                ->post("{$this->baseUrl}/transaction/initialize", [
                    'email' => $email,
                    'amount' => $amountKobo, // integer amount in kobo
                    'reference' => $reference,
                    'callback_url' => $callbackUrl,
                ]);
        } catch (\Throwable $e) {
            Log::error('Paystack initializePayment exception', [
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number ?? null,
                'reference' => $reference,
                'email' => $email,
                'amount_kobo' => $amountKobo,
                'base_url' => $this->baseUrl,
                'callback_url' => $callbackUrl,
                'key_configured' => $this->secretKey !== '',
                'key_prefix' => $keyPrefix,
                'exception' => $e->getMessage(),
            ]);

            return ['status' => false, 'message' => $e->getMessage()];
        }

        $json = $response->json();

        if ($response->failed() || !is_array($json) || empty($json['status']) || empty($json['data']['authorization_url'])) {
            Log::error('Paystack initializePayment failed', [
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number ?? null,
                'reference' => $reference,
                'email' => $email,
                'amount_kobo' => $amountKobo,
                'base_url' => $this->baseUrl,
                'callback_url' => $callbackUrl,
                'key_configured' => $this->secretKey !== '',
                'key_prefix' => $keyPrefix,
                'http_status' => $response->status(),
                'response' => is_array($json) ? $json : $response->body(),
            ]);
        } else {
            $json['data']['reference'] = $json['data']['reference'] ?? $reference;
        }

        return $json;
    }

    public function verifyPayment($reference)
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->get("{$this->baseUrl}/transaction/verify/{$reference}");
        } catch (\Throwable $e) {
            Log::error('Paystack verifyPayment exception', [
                'reference' => $reference,
                'exception' => $e->getMessage(),
            ]);

            return ['status' => false, 'message' => $e->getMessage()];
        }

        $json = $response->json();

        if ($response->failed() || !is_array($json) || empty($json['status'])) {
            Log::error('Paystack verifyPayment failed', [
                'reference' => $reference,
                'http_status' => $response->status(),
                'response' => is_array($json) ? $json : $response->body(),
            ]);
        }

        return $json;
    }
}
