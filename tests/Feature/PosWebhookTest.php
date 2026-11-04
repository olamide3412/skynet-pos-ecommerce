<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PosConnection;
use App\Models\PosSyncLog;
use App\Models\Product;
use App\Models\SyncedPosItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected string $secret = 'test_webhook_secret_key_12345';
    protected int $branchId = 1;

    protected function createConnection(int $branchId = 1, ?string $secret = 'test_webhook_secret_key_12345'): PosConnection
    {
        $conn = new PosConnection();
        $conn->pos_base_url = 'https://pos.skynetdigital.com/api/commerce/v1';
        $conn->client_id = 'test_client_id';
        $conn->api_secret = $secret;
        $conn->pos_branch_id = $branchId;
        $conn->pos_branch_name = 'Test POS Branch';
        $conn->is_connected = true;
        $conn->save();

        return $conn;
    }

    public function test_rejects_with_403_when_no_pos_connection_configured(): void
    {
        $payload = [
            'event'     => 'test.ping',
            'branch_id' => 1,
        ];

        $response = $this->postJson('/api/webhooks/pos-inventory', $payload, [
            'X-Skynet-Event' => 'test.ping',
            'X-Branch-Id'    => '1',
            'Authorization'  => 'Bearer dummy_token',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized: No POS connection configured on Ecommerce store.',
            ]);

        $this->assertDatabaseHas('pos_sync_logs', [
            'type'   => 'webhook_auth',
            'status' => 'failed',
        ]);
    }

    public function test_rejects_with_403_when_branch_id_mismatches(): void
    {
        $this->createConnection($this->branchId, $this->secret);

        $payload = [
            'event'     => 'test.ping',
            'branch_id' => 99,
        ];

        $response = $this->postJson('/api/webhooks/pos-inventory', $payload, [
            'X-Skynet-Event' => 'test.ping',
            'X-Branch-Id'    => '99',
            'Authorization'  => 'Bearer ' . $this->secret,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => "Unauthorized branch: This store is paired with POS Branch #{$this->branchId}.",
            ]);

        $this->assertDatabaseHas('pos_sync_logs', [
            'type'   => 'webhook_auth',
            'status' => 'failed',
        ]);
    }

    public function test_rejects_with_403_when_branch_id_is_missing(): void
    {
        $this->createConnection($this->branchId, $this->secret);

        $payload = [
            'event' => 'test.ping',
        ];

        $response = $this->postJson('/api/webhooks/pos-inventory', $payload, [
            'X-Skynet-Event' => 'test.ping',
            'Authorization'  => 'Bearer ' . $this->secret,
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('pos_sync_logs', [
            'type'   => 'webhook_auth',
            'status' => 'failed',
        ]);
    }

    public function test_rejects_with_401_when_token_and_signature_are_invalid(): void
    {
        $this->createConnection($this->branchId, $this->secret);

        $category = Category::create(['name' => 'Tech', 'slug' => 'tech']);
        $product = Product::create([
            'category_id'      => $category->id,
            'name'             => 'Test Item',
            'slug'             => 'test-item',
            'url_key'          => 'test-item',
            'sku'              => 'SKU-001',
            'stock'            => 10,
            'price'            => 1500,
            'inventory_source' => 'pos',
            'pos_item_id'      => 667,
        ]);

        $payload = [
            'event'     => 'inventory.updated',
            'branch_id' => 1,
            'data'      => [
                'pos_item_id'   => 667,
                'available_qty' => 999,
                'price'         => 9999,
            ],
        ];

        $response = $this->postJson('/api/webhooks/pos-inventory', $payload, [
            'X-Skynet-Event'     => 'inventory.updated',
            'X-Branch-Id'        => '1',
            'Authorization'      => 'Bearer wrong_token',
            'X-Skynet-Signature' => 'sha256=invalid_hash',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized: Invalid POS webhook credentials or signature.',
            ]);

        // Verify product was NOT mutated
        $this->assertEquals(10, $product->fresh()->stock);
        $this->assertEquals(1500, $product->fresh()->price);

        $this->assertDatabaseHas('pos_sync_logs', [
            'type'   => 'webhook_auth',
            'status' => 'failed',
        ]);
    }

    public function test_accepts_webhook_with_valid_bearer_token(): void
    {
        $this->createConnection($this->branchId, $this->secret);

        $payload = [
            'event'     => 'test.ping',
            'branch_id' => 1,
        ];

        $response = $this->postJson('/api/webhooks/pos-inventory', $payload, [
            'X-Skynet-Event' => 'test.ping',
            'X-Branch-Id'    => '1',
            'Authorization'  => 'Bearer ' . $this->secret,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('pos_sync_logs', [
            'type'   => 'webhook_ping',
            'status' => 'success',
        ]);
    }

    public function test_accepts_webhook_with_valid_x_commerce_key(): void
    {
        $this->createConnection($this->branchId, $this->secret);

        $payload = [
            'event'     => 'test.ping',
            'branch_id' => 1,
        ];

        $response = $this->postJson('/api/webhooks/pos-inventory', $payload, [
            'X-Skynet-Event' => 'test.ping',
            'X-Branch-Id'    => '1',
            'X-Commerce-Key' => $this->secret,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_accepts_webhook_with_valid_hmac_signature(): void
    {
        $this->createConnection($this->branchId, $this->secret);

        $payload = [
            'event'     => 'test.ping',
            'branch_id' => 1,
        ];

        $jsonPayload = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $jsonPayload, $this->secret);

        $response = $this->call(
            'POST',
            '/api/webhooks/pos-inventory',
            [],
            [],
            [],
            [
                'CONTENT_TYPE'           => 'application/json',
                'HTTP_ACCEPT'            => 'application/json',
                'HTTP_X_SKYNET_EVENT'    => 'test.ping',
                'HTTP_X_BRANCH_ID'       => '1',
                'HTTP_X_SKYNET_SIGNATURE'=> $signature,
            ],
            $jsonPayload
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_update_product_stock_and_price_via_authenticated_webhook(): void
    {
        $this->createConnection($this->branchId, $this->secret);

        $category = Category::create([
            'name' => 'Tech',
            'slug' => 'tech',
        ]);

        $product = Product::create([
            'category_id'      => $category->id,
            'name'             => 'Test Item',
            'slug'             => 'test-item',
            'url_key'          => 'test-item',
            'sku'              => 'SKU-001',
            'stock'            => 10,
            'price'            => 1500,
            'inventory_source' => 'pos',
            'pos_item_id'      => 667,
        ]);

        $syncedItem = SyncedPosItem::create([
            'pos_item_id'            => 667,
            'pos_item_name'          => 'Test Item',
            'sku'                    => 'SKU-001',
            'barcode'                => 'SKU-001',
            'effective_availability' => 10,
            'standard_price'         => 1500,
        ]);

        $payload = [
            'event'     => 'inventory.updated',
            'branch_id' => 1,
            'data'      => [
                'pos_item_id'    => 667,
                'item_name'      => 'Test Item',
                'barcode_number' => 'SKU-001',
                'available_qty'  => 45,
                'price'          => 2000,
            ],
        ];

        $jsonPayload = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $jsonPayload, $this->secret);

        $response = $this->call(
            'POST',
            '/api/webhooks/pos-inventory',
            [],
            [],
            [],
            [
                'CONTENT_TYPE'           => 'application/json',
                'HTTP_ACCEPT'            => 'application/json',
                'HTTP_X_SKYNET_EVENT'    => 'inventory.updated',
                'HTTP_X_BRANCH_ID'       => '1',
                'HTTP_AUTHORIZATION'     => 'Bearer ' . $this->secret,
                'HTTP_X_COMMERCE_KEY'    => $this->secret,
                'HTTP_X_SKYNET_SIGNATURE'=> $signature,
            ],
            $jsonPayload
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'stock'   => 45,
                'price'   => 2000,
            ]);

        $this->assertEquals(45, $product->fresh()->stock);
        $this->assertEquals(2000, $product->fresh()->price);
        $this->assertEquals(45, $syncedItem->fresh()->effective_availability);
        $this->assertEquals(2000, $syncedItem->fresh()->standard_price);

        $this->assertDatabaseHas('pos_sync_logs', [
            'type'   => 'webhook_inventory',
            'status' => 'success',
        ]);
    }
}
