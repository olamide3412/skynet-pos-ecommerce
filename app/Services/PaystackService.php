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

    public function initializePayment($order, $email)
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", [
                'email' => $email,
                'amount' => $order->total_amount * 100, // amount in kobo
                'reference' => 'ps_' . uniqid() . '_' . $order->id,
                // Make sure to define payment.callback route later
                'callback_url' => route('payment.callback', ['provider' => 'paystack']),
            ]);

        return $response->json();
    }

    public function verifyPayment($reference)
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        return $response->json();
    }
}
