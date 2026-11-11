<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FlutterwaveService
{
    protected $secretKey;
    protected $secretHash;
    protected $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.flutterwave.secret_key');
        $this->secretHash = config('services.flutterwave.secret_hash');
        $this->baseUrl = config('services.flutterwave.base_url', 'https://api.flutterwave.com/v3');
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
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/payments", [
                'tx_ref' => $tx_ref,
                'amount' => $order->total_amount,
                'currency' => 'NGN',
                'redirect_url' => route('payment.callback', ['provider' => 'flutterwave']),
                'customer' => [
                    'email' => $email,
                    'name' => $name,
                ],
                'customizations' => [
                    'title' => config('app.name'),
                    'description' => 'Payment for Order #' . $order->id,
                ]
            ]);

        return $response->json();
    }

    public function verifyPayment($transactionId)
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transactions/{$transactionId}/verify");

        return $response->json();
    }
}
