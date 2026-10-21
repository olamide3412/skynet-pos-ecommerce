<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SyncedPosItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_receive_ping_webhook(): void
    {
        $response = $this->postJson('/api/webhooks/pos-inventory', [
            'event'     => 'test.ping',
            'branch_id' => 1,
        ], [
            'X-Skynet-Event' => 'test.ping',
            'X-Branch-Id'    => '1',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_update_product_stock_via_webhook(): void
    {
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

        $response = $this->postJson('/api/webhooks/pos-inventory', [
            'event'     => 'inventory.updated',
            'branch_id' => 1,
            'data'      => [
                'pos_item_id'    => 667,
                'item_name'      => 'Test Item',
                'barcode_number' => 'SKU-001',
                'available_qty'  => 45,
                'price'          => 2000,
            ],
        ], [
            'X-Skynet-Event' => 'inventory.updated',
            'X-Branch-Id'    => '1',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'        => true,
                'stock'          => 45,
                'price'          => 2000,
            ]);

        $this->assertEquals(45, $product->fresh()->stock);
        $this->assertEquals(2000, $product->fresh()->price);
    }
}
