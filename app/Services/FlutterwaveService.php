<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwaveService
{
    protected $secretKey;
    protected $secretHash;
    protected $baseUrl;

    public function __construct()
    {
        $this->secretKey = trim((string) config('services.flutterwave.secret_key'));
        $this->secretHash = trim((string) config('services.flutterwave.secret_hash'));
        $this->baseUrl = rtrim((string) config('services.flutterwave.base_url', 'https://api.flutterwave.com/v3'), '/');
    }

    /**
     * Verify whether the incoming Flutterwave webhook signature matches the configured secret hash.
     */
    public function isValidWebhookSignature(?string $signature): bool
    {
        if (empty($this->secretHash) || empty($signature)) {
            return false;
        }

        return hash_equals((string) $this->secretHash, (string) $signature);
    }

    public function initializePayment($order, $email, $name)
    {
        $tx_ref = 'fw_' . uniqid() . '_' . $order->id;
        $amount = round((float) $order->total_amount, 2);
        $redirectUrl = route('payment.callback', ['provider' => 'flutterwave']);
        $keyPrefix = $this->secretKey !== '' ? substr($this->secretKey, 0, 12) : '(empty)';

        try {
            $response = Http::withToken($this->secretKey)
                ->post("{$this->baseUrl}/payments", [
                    'tx_ref' => $tx_ref,
                    'amount' => $amount,
                    'currency' => 'NGN',
                    'redirect_url' => $redirectUrl,
                    'customer' => [
                        'email' => $email,
                        'name' => $name,
                    ],
                    'customizations' => [
                        'title' => config('app.name'),
                        'description' => 'Payment for Order #' . $order->tracking_number,
                    ]
                ]);
        } catch (\Throwable $e) {
            Log::error('Flutterwave initializePayment exception', [
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number ?? null,
                'tx_ref' => $tx_ref,
                'email' => $email,
                'amount' => $amount,
                'base_url' => $this->baseUrl,
                'redirect_url' => $redirectUrl,
                'key_configured' => $this->secretKey !== '',
                'key_prefix' => $keyPrefix,
                'exception' => $e->getMessage(),
            ]);

            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        $json = $response->json();

        if ($response->failed() || !is_array($json) || ($json['status'] ?? null) !== 'success' || empty($json['data']['link'])) {
            Log::error('Flutterwave initializePayment failed', [
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number ?? null,
                'tx_ref' => $tx_ref,
                'email' => $email,
                'amount' => $amount,
                'base_url' => $this->baseUrl,
                'redirect_url' => $redirectUrl,
                'key_configured' => $this->secretKey !== '',
                'key_prefix' => $keyPrefix,
                'http_status' => $response->status(),
                'response' => is_array($json) ? $json : $response->body(),
            ]);
        } elseif (is_array($json)) {
            $json['data']['tx_ref'] = $tx_ref;
        }

        return $json;
    }

    public function verifyPayment($transactionIdOrRef)
    {
        try {
            if (is_numeric($transactionIdOrRef)) {
                $response = Http::withToken($this->secretKey)
                    ->get("{$this->baseUrl}/transactions/{$transactionIdOrRef}/verify");
            } else {
                $response = Http::withToken($this->secretKey)
                    ->get("{$this->baseUrl}/transactions/verify_by_reference", [
                        'tx_ref' => $transactionIdOrRef,
                    ]);
            }
        } catch (\Throwable $e) {
            Log::error('Flutterwave verifyPayment exception', [
                'reference' => $transactionIdOrRef,
                'exception' => $e->getMessage(),
            ]);

            return ['status' => 'error', 'message' => $e->getMessage()];
        }

        $json = $response->json();

        if ($response->failed() || !is_array($json) || ($json['status'] ?? null) !== 'success') {
            Log::error('Flutterwave verifyPayment failed', [
                'reference' => $transactionIdOrRef,
                'http_status' => $response->status(),
                'response' => is_array($json) ? $json : $response->body(),
            ]);
        }

        return $json;
    }
}
