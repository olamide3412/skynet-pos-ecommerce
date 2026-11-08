<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentWebhookActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/reservations*' => Http::response(['status' => true, 'data' => ['id' => 1]], 200),
        ]);

        Config::set('services.paystack.secret_key', 'sk_test_paystack_secret_key_12345');
        Config::set('services.flutterwave.secret_hash', 'flw_secret_hash_test_67890');
    }

    protected function createOrder(string $trackingNumber, string $reference, float $amount = 25000): Order
    {
        $category = Category::create([
            'name' => 'Solar Equipment',
            'slug' => 'solar-equipment-' . uniqid(),
        ]);

        $product = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Solar Battery 100Ah',
            'slug'           => 'solar-battery-100ah-' . uniqid(),
            'url_key'        => 'solar-battery-100ah-' . uniqid(),
            'product_number' => 'BAT-' . uniqid(),
            'price'          => $amount,
            'status'         => true,
            'stock'          => 15,
        ]);

        $order = Order::create([
            'tracking_number'   => $trackingNumber,
            'payment_reference' => $reference,
            'billing_name'      => 'Alice Johnson',
            'billing_email'     => 'alice@example.com',
            'total_amount'      => $amount,
            'subtotal'          => $amount,
            'status'            => 'pending',
            'payment_method'    => 'paystack',
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'price'      => $amount,
            'quantity'   => 1,
        ]);

        return $order;
    }

    public function test_paystack_webhook_invalid_signature_is_rejected_and_logged(): void
    {
        $payload = [
            'event' => 'charge.success',
            'data'  => ['reference' => 'ref_invalid_sig_123'],
        ];

        $response = $this->postJson('/api/webhooks/paystack', $payload, [
            'x-paystack-signature' => 'invalid-signature-hash',
        ]);

        $response->assertStatus(401);

        $log = ActivityLog::where('action_type', 'webhook')
            ->where('actor_name', 'Paystack Webhook')
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'An activity log entry must be created for rejected Paystack webhook');
        $this->assertStringContainsString('Rejected Paystack webhook: Invalid signature', $log->description);
        $this->assertEquals('system', $log->actor_type);
    }

    public function test_paystack_webhook_valid_payment_updates_order_and_creates_activity_log(): void
    {
        $order = $this->createOrder('TRK-PAYSTACK-001', 'ref_paystack_success_001', 35000);

        $payload = [
            'event' => 'charge.success',
            'data'  => [
                'reference' => 'ref_paystack_success_001',
                'amount'    => 3500000, // 35,000 in kobo
                'currency'  => 'NGN',
                'status'    => 'success',
                'channel'   => 'card',
                'customer'  => [
                    'email' => 'alice@example.com',
                ],
            ],
        ];

        $rawBody = json_encode($payload);
        $validSignature = hash_hmac('sha512', $rawBody, 'sk_test_paystack_secret_key_12345');

        $response = $this->call(
            'POST',
            '/api/webhooks/paystack',
            [],
            [],
            [],
            [
                'CONTENT_TYPE'           => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $validSignature,
            ],
            $rawBody
        );

        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals('processing', $order->status);
        $this->assertEquals('paystack', $order->payment_method);

        $log = ActivityLog::where('action_type', 'webhook')
            ->where('actor_name', 'Paystack Webhook')
            ->where('description', 'like', '%TRK-PAYSTACK-001%')
            ->first();

        $this->assertNotNull($log, 'Activity log entry must be created for verified Paystack webhook payment');
        $this->assertStringContainsString('Paystack webhook: Verified payment', $log->description);
        $this->assertEquals('system', $log->actor_type);
        $this->assertEquals('ref_paystack_success_001', $log->details['reference']);
        $this->assertEquals(35000, $log->details['amount_paid']);
        $this->assertEquals($order->id, $log->details['order_id']);
    }

    public function test_paystack_webhook_other_event_is_logged_in_activity_logs(): void
    {
        $payload = [
            'event' => 'transfer.success',
            'data'  => [
                'recipient' => 'RCP_12345678',
                'amount'    => 500000,
            ],
        ];

        $rawBody = json_encode($payload);
        $validSignature = hash_hmac('sha512', $rawBody, 'sk_test_paystack_secret_key_12345');

        $response = $this->call(
            'POST',
            '/api/webhooks/paystack',
            [],
            [],
            [],
            [
                'CONTENT_TYPE'              => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $validSignature,
            ],
            $rawBody
        );

        $response->assertStatus(200);

        $log = ActivityLog::where('action_type', 'webhook')
            ->where('actor_name', 'Paystack Webhook')
            ->where('description', 'like', '%transfer.success%')
            ->first();

        $this->assertNotNull($log, 'Non-charge Paystack events should still be audited in ActivityLog');
        $this->assertEquals('transfer.success', $log->details['event']);
    }

    public function test_flutterwave_webhook_invalid_signature_is_rejected_and_logged(): void
    {
        $payload = [
            'event' => 'charge.completed',
            'data'  => ['tx_ref' => 'flw_tx_ref_invalid_999'],
        ];

        $response = $this->postJson('/api/webhooks/flutterwave', $payload, [
            'verif-hash' => 'wrong-secret-hash',
        ]);

        $response->assertStatus(401);

        $log = ActivityLog::where('action_type', 'webhook')
            ->where('actor_name', 'Flutterwave Webhook')
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'An activity log entry must be created for rejected Flutterwave webhook');
        $this->assertStringContainsString('Rejected Flutterwave webhook: Invalid signature', $log->description);
        $this->assertEquals('system', $log->actor_type);
    }

    public function test_flutterwave_webhook_valid_payment_updates_order_and_creates_activity_log(): void
    {
        $order = $this->createOrder('TRK-FLW-002', 'flw_tx_ref_002', 45000);

        $payload = [
            'event' => 'charge.completed',
            'data'  => [
                'tx_ref'   => 'flw_tx_ref_002',
                'flw_ref'  => 'FLW_MOCK_112233',
                'amount'   => 45000,
                'currency' => 'NGN',
                'status'   => 'successful',
                'customer' => [
                    'email' => 'alice@example.com',
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/flutterwave', $payload, [
            'verif-hash' => 'flw_secret_hash_test_67890',
        ]);

        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals('processing', $order->status);
        $this->assertEquals('flutterwave', $order->payment_method);

        $log = ActivityLog::where('action_type', 'webhook')
            ->where('actor_name', 'Flutterwave Webhook')
            ->where('description', 'like', '%TRK-FLW-002%')
            ->first();

        $this->assertNotNull($log, 'Activity log entry must be created for verified Flutterwave webhook payment');
        $this->assertStringContainsString('Flutterwave webhook: Verified payment', $log->description);
        $this->assertEquals('system', $log->actor_type);
        $this->assertEquals('flw_tx_ref_002', $log->details['tx_ref']);
        $this->assertEquals(45000, $log->details['amount_paid']);
        $this->assertEquals($order->id, $log->details['order_id']);
    }

    public function test_flutterwave_webhook_other_event_is_logged_in_activity_logs(): void
    {
        $payload = [
            'event' => 'charge.failed',
            'data'  => [
                'tx_ref' => 'failed_ref_888',
                'status' => 'failed',
            ],
        ];

        $response = $this->postJson('/api/webhooks/flutterwave', $payload, [
            'verif-hash' => 'flw_secret_hash_test_67890',
        ]);

        $response->assertStatus(200);

        $log = ActivityLog::where('action_type', 'webhook')
            ->where('actor_name', 'Flutterwave Webhook')
            ->where('description', 'like', '%charge.failed%')
            ->first();

        $this->assertNotNull($log, 'Failed/other Flutterwave events must be logged in ActivityLog');
        $this->assertEquals('charge.failed', $log->details['event']);
    }
}

