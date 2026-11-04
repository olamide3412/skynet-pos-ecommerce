<?php

use App\Http\Controllers\Api\PosWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// POS Real-time Inventory & Price Webhook
Route::post('/webhooks/pos-inventory', [PosWebhookController::class, 'handle']);

// Payment Gateway Webhooks (Alternative API paths)
Route::post('/webhooks/paystack', [\App\Http\Controllers\Frontend\CheckoutController::class, 'paystackWebhook']);
Route::post('/webhooks/flutterwave', [\App\Http\Controllers\Frontend\CheckoutController::class, 'flutterwaveWebhook']);
