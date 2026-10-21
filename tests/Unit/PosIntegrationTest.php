<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Product;
use App\Models\ProductPosUnit;
use App\Models\PosConnection;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Crypt;

class PosIntegrationTest extends TestCase
{
    public function test_pos_connection_encrypts_and_hides_secret(): void
    {
        $connection = new PosConnection([
            'pos_branch_id' => 10,
            'pos_branch_name' => 'Main Warehouse Branch',
            'api_base_url' => 'https://pos.skynetdigital.test/api/v1',
            'api_key' => 'pos_key_test123',
            'encrypted_api_secret' => Crypt::encryptString('super_secret_pos_token_xyz'),
            'status' => 'connected',
        ]);

        // Secret should be decryptable via service method or Crypt
        $decrypted = Crypt::decryptString($connection->encrypted_api_secret);
        $this->assertEquals('super_secret_pos_token_xyz', $decrypted);

        // Secret must not appear in JSON serialized representation
        $json = $connection->toJson();
        $this->assertStringNotContainsString('super_secret_pos_token_xyz', $json);
        $this->assertStringNotContainsString('encrypted_api_secret', $json);
    }

    public function test_product_dual_inventory_source_detection(): void
    {
        $posProduct = new Product([
            'name' => 'POS Item Alpha',
            'status' => true,
            'inventory_source' => 'pos',
            'pos_item_id' => 123,
            'pos_sync_status' => 'synced',
            'stock' => 15,
        ]);

        $this->assertTrue($posProduct->isPosLinked());
        $this->assertTrue($posProduct->isAvailableForPurchase());

        $missingPosProduct = new Product([
            'name' => 'POS Item Missing',
            'status' => true,
            'inventory_source' => 'pos',
            'pos_item_id' => 456,
            'pos_sync_status' => 'missing_pos_item',
            'stock' => 10, // Stock cached, but purchase disabled because POS item missing
        ]);

        $this->assertTrue($missingPosProduct->isPosLinked());
        $this->assertFalse($missingPosProduct->isAvailableForPurchase());

        $ecommerceProduct = new Product([
            'name' => 'Local Merch T-Shirt',
            'status' => true,
            'inventory_source' => 'ecommerce',
            'stock' => 20,
        ]);

        $this->assertFalse($ecommerceProduct->isPosLinked());
        $this->assertTrue($ecommerceProduct->isAvailableForPurchase());
    }

    public function test_product_effective_price_for_pos_units(): void
    {
        $product = new Product([
            'name' => 'Soft Drink Can',
            'price' => 500,
            'discount_price' => 450,
            'inventory_source' => 'pos',
        ]);

        $baseUnit = new ProductPosUnit([
            'unit_kind' => 'base',
            'unit_name' => 'Can',
            'quantity_in_base_units' => 1,
            'synced_standard_price' => 450,
            'is_enabled_online' => true,
        ]);

        $packUnit = new ProductPosUnit([
            'unit_kind' => 'secondary',
            'unit_name' => 'Pack of 12',
            'quantity_in_base_units' => 12,
            'synced_standard_price' => 5200,
            'is_enabled_online' => true,
        ]);

        // Base unit uses product discounted price
        $this->assertEquals(450.0, $product->getEffectivePriceForUnit($baseUnit));

        // Secondary pack unit uses its own standard price
        $this->assertEquals(5200.0, $product->getEffectivePriceForUnit($packUnit));
    }

    public function test_order_item_pos_snapshot_fields(): void
    {
        $orderItem = new OrderItem([
            'order_id' => 1,
            'product_id' => 10,
            'quantity' => 2,
            'price' => 5200,
            'inventory_source' => 'pos',
            'pos_item_id' => 123,
            'pos_item_unit_id' => 45,
            'unit_name' => 'Pack of 12',
            'quantity_in_base_units' => 12,
            'standard_pos_price_snapshot' => 5200,
            'online_unit_price' => 5200,
        ]);

        $this->assertEquals('pos', $orderItem->inventory_source);
        $this->assertEquals(123, $orderItem->pos_item_id);
        $this->assertEquals('Pack of 12', $orderItem->unit_name);
        $this->assertEquals('12.0000', $orderItem->quantity_in_base_units);
    }

    public function test_reservation_service_handles_pure_ecommerce_cart_without_calling_pos(): void
    {
        $mockConnection = new PosConnection();
        $apiClient = new \App\Services\PosIntegration\PosApiClient($mockConnection);
        $reservationService = new \App\Services\PosIntegration\PosReservationService($apiClient);

        $cart = new \App\Models\Cart();
        $cart->setRelation('items', collect([
            new \App\Models\CartItem([
                'id' => 1,
                'quantity' => 2,
            ])
        ]));
        // Attach product with ecommerce inventory source
        $cart->items[0]->setRelation('product', new Product([
            'id' => 99,
            'name' => 'Custom Handcrafted Gift',
            'inventory_source' => 'ecommerce',
            'stock' => 10,
            'status' => true,
        ]));

        $result = $reservationService->reserveForCart($cart);

        $this->assertTrue($result['status']);
        $this->assertFalse($result['has_pos_items']);
        $this->assertEquals(0, $result['items_reserved']);
        $this->assertNotEmpty($result['inventory_reference']);
    }

    public function test_release_reservation_skips_when_no_pos_items(): void
    {
        $mockConnection = new PosConnection();
        $apiClient = new \App\Services\PosIntegration\PosApiClient($mockConnection);
        $reservationService = new \App\Services\PosIntegration\PosReservationService($apiClient);

        $order = new \App\Models\Order([
            'has_pos_items' => false,
            'pos_reservation_reference' => null,
            'pos_reservation_status' => 'none',
        ]);

        $released = $reservationService->releaseReservation($order);
        $this->assertTrue($released);
    }

    public function test_synced_pos_item_casts_availability_as_integer(): void
    {
        $item = new \App\Models\SyncedPosItem([
            'pos_item_id' => 999,
            'pos_item_name' => 'Test Item',
            'effective_availability' => '34.00',
            'standard_price' => 1500.50,
        ]);

        $this->assertIsInt($item->effective_availability);
        $this->assertSame(34, $item->effective_availability);

        $array = $item->toArray();
        $this->assertIsInt($array['effective_availability']);
        $this->assertSame(34, $array['effective_availability']);
    }
}

