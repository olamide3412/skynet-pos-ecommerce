<?php

namespace Tests\Feature;

use App\Enums\RoleEnums;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPosUnit;
use App\Models\SyncedPosItem;
use App\Models\SyncedPosItemUnit;
use App\Models\User;
use App\Services\PosIntegration\PosCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosProductUnitSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Category $category;
    protected PosCatalogService $catalogService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => RoleEnums::User->value,
        ]);

        $this->category = Category::create([
            'name' => 'General',
            'slug' => 'general',
        ]);

        $this->catalogService = app(PosCatalogService::class);
    }

    public function test_sync_product_units_purges_deleted_pos_units_without_affecting_past_orders(): void
    {
        // 1. Create SyncedPosItem with Base unit + 2 Additional units (Pack and Carton)
        $syncedItem = SyncedPosItem::create([
            'pos_item_id' => 9901,
            'pos_item_name' => 'Premium Milo Refill',
            'base_unit_name' => 'Tin',
            'standard_price' => 3000,
            'buying_price' => 2400,
            'mapping_status' => 'mapped_simple',
        ]);

        SyncedPosItemUnit::create([
            'synced_pos_item_id' => $syncedItem->id,
            'pos_item_unit_id' => null,
            'unit_kind' => 'base',
            'unit_name' => 'Tin',
            'quantity_in_base_units' => 1.0,
            'standard_price' => 3000,
        ]);

        $packUnit = SyncedPosItemUnit::create([
            'synced_pos_item_id' => $syncedItem->id,
            'pos_item_unit_id' => 501,
            'unit_kind' => 'additional',
            'unit_name' => 'Pack of 6',
            'quantity_in_base_units' => 6.0,
            'standard_price' => 17500,
        ]);

        $cartonUnit = SyncedPosItemUnit::create([
            'synced_pos_item_id' => $syncedItem->id,
            'pos_item_unit_id' => 502,
            'unit_kind' => 'additional',
            'unit_name' => 'Carton of 24',
            'quantity_in_base_units' => 24.0,
            'standard_price' => 68000,
        ]);

        // 2. Create Mapped Product
        $product = Product::create([
            'name' => 'Premium Milo Refill',
            'slug' => 'premium-milo-refill',
            'url_key' => 'premium-milo-refill',
            'price' => 3000,
            'cost_price' => 2400,
            'stock' => 100,
            'inventory_source' => 'pos',
            'pos_item_id' => 9901,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        // Initial sync of units
        $this->catalogService->syncProductUnits($product, $syncedItem);

        // Verify all 3 units exist in product_pos_units
        $this->assertDatabaseHas('product_pos_units', [
            'product_id' => $product->id,
            'unit_kind' => 'base',
            'unit_name' => 'Tin',
        ]);
        $this->assertDatabaseHas('product_pos_units', [
            'product_id' => $product->id,
            'pos_item_unit_id' => 501,
            'unit_name' => 'Pack of 6',
        ]);
        $this->assertDatabaseHas('product_pos_units', [
            'product_id' => $product->id,
            'pos_item_unit_id' => 502,
            'unit_name' => 'Carton of 24',
        ]);

        $packPosUnit = ProductPosUnit::where('product_id', $product->id)->where('pos_item_unit_id', 501)->first();

        // 3. Create a past order where customer bought "Pack of 6"
        $order = Order::create([
            'tracking_number' => 'SKY-TEST-9901',
            'total_amount' => 17500,
            'status' => 'completed',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 17500,
            'cost_price' => 14000,
            'inventory_source' => 'pos',
            'pos_item_id' => 9901,
            'pos_item_unit_id' => 501,
            'unit_name' => 'Pack of 6',
            'quantity_in_base_units' => 6.0,
            'online_unit_price' => 17500,
        ]);

        // 4. Create an active cart with "Pack of 6"
        $cart = Cart::create(['session_id' => 'test-session-123']);
        $cartItem = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 17500,
            'product_pos_unit_id' => $packPosUnit->id,
            'unit_name' => 'Pack of 6',
        ]);

        // 5. Simulate POS deletion: "Pack of 6" (501) was deleted on POS, so syncedItem units only have Base and Carton (502)
        $packUnit->delete();

        // Run syncProductUnits
        $this->catalogService->syncProductUnits($product, $syncedItem);

        // 6. Assertions:
        // A) Pack of 6 was removed from product_pos_units
        $this->assertDatabaseMissing('product_pos_units', [
            'product_id' => $product->id,
            'pos_item_unit_id' => 501,
        ]);

        // B) Carton of 24 and Base unit still exist
        $this->assertDatabaseHas('product_pos_units', [
            'product_id' => $product->id,
            'pos_item_unit_id' => 502,
            'unit_name' => 'Carton of 24',
        ]);
        $this->assertDatabaseHas('product_pos_units', [
            'product_id' => $product->id,
            'unit_kind' => 'base',
            'unit_name' => 'Tin',
        ]);

        // C) Historical order item snapshot is 100% intact!
        $orderItem->refresh();
        $this->assertEquals('Pack of 6', $orderItem->unit_name);
        $this->assertEquals(17500, $orderItem->price);
        $this->assertEquals(6.0, (float) $orderItem->quantity_in_base_units);

        // D) Stale cart item referencing the deleted unit was cleaned up
        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItem->id,
        ]);
    }

    public function test_modifying_pos_unit_updates_price_and_multiplier_properly(): void
    {
        $syncedItem = SyncedPosItem::create([
            'pos_item_id' => 9902,
            'pos_item_name' => 'Coca Cola Bottle',
            'base_unit_name' => 'Bottle',
            'standard_price' => 300,
            'buying_price' => 200,
            'mapping_status' => 'mapped_simple',
        ]);

        $packUnit = SyncedPosItemUnit::create([
            'synced_pos_item_id' => $syncedItem->id,
            'pos_item_unit_id' => 601,
            'unit_kind' => 'additional',
            'unit_name' => 'Pack of 12',
            'quantity_in_base_units' => 12.0,
            'standard_price' => 3400,
        ]);

        $product = Product::create([
            'name' => 'Coca Cola Bottle',
            'slug' => 'coca-cola-bottle',
            'url_key' => 'coca-cola-bottle',
            'price' => 300,
            'cost_price' => 200,
            'stock' => 50,
            'inventory_source' => 'pos',
            'pos_item_id' => 9902,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        $this->catalogService->syncProductUnits($product, $syncedItem);

        // Verify initial price
        $this->assertDatabaseHas('product_pos_units', [
            'product_id' => $product->id,
            'pos_item_unit_id' => 601,
            'unit_name' => 'Pack of 12',
            'synced_standard_price' => 3400,
            'quantity_in_base_units' => 12.0,
        ]);

        // Modify unit on POS (price increase to 3600, renamed to Crate of 12)
        $packUnit->update([
            'unit_name' => 'Crate of 12',
            'standard_price' => 3600,
        ]);

        $this->catalogService->syncProductUnits($product, $syncedItem);

        // Verify updated price and name, without duplicate records
        $this->assertDatabaseHas('product_pos_units', [
            'product_id' => $product->id,
            'pos_item_unit_id' => 601,
            'unit_name' => 'Crate of 12',
            'synced_standard_price' => 3600,
        ]);

        $this->assertEquals(1, ProductPosUnit::where('product_id', $product->id)->where('unit_kind', 'additional')->count());
    }

    public function test_admin_can_update_product_pos_units_with_custom_image_and_label(): void
    {
        $admin = User::factory()->create([
            'role' => RoleEnums::Administrator->value,
        ]);

        $syncedItem = SyncedPosItem::create([
            'pos_item_id' => 9903,
            'pos_item_name' => 'Peak Milk 50g',
            'base_unit_name' => 'Tin',
            'standard_price' => 500,
            'mapping_status' => 'mapped_simple',
        ]);

        $product = Product::create([
            'name' => 'Peak Milk 50g',
            'slug' => 'peak-milk-50g-test',
            'url_key' => 'peak-milk-50g-test',
            'price' => 500,
            'stock' => 100,
            'inventory_source' => 'pos',
            'pos_item_id' => 9903,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        $this->catalogService->syncProductUnits($product, $syncedItem);

        $baseUnit = ProductPosUnit::where('product_id', $product->id)->first();
        $this->assertNotNull($baseUnit);

        $payload = [
            'inventory_source' => 'pos',
            'pos_item_id' => 9903,
            'name' => 'Peak Milk 50g Updated',
            'category_id' => $this->category->id,
            'url_key' => 'peak-milk-50g-test',
            'price' => 500,
            'stock' => 100,
            'pos_units' => json_encode([
                [
                    'id' => $baseUnit->id,
                    'online_label' => 'Single Tin (50g)',
                    'is_enabled_online' => true,
                    'image' => 'products/peak-tin.webp',
                ]
            ]),
        ];

        $response = $this->actingAs($admin, 'web')->put(route('admin.products.update', $product->id), $payload);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $baseUnit->refresh();
        $this->assertEquals('Single Tin (50g)', $baseUnit->online_label);
        $this->assertEquals('products/peak-tin.webp', $baseUnit->image);
        $this->assertTrue($baseUnit->is_enabled_online);
    }

    public function test_admin_can_create_and_update_selling_units_on_ecommerce_only_product(): void
    {
        $admin = User::factory()->create([
            'role' => RoleEnums::Administrator->value,
        ]);

        $product = Product::create([
            'name' => 'Artisan Coffee Beans',
            'slug' => 'artisan-coffee-beans',
            'url_key' => 'artisan-coffee-beans',
            'price' => 2000,
            'stock' => 200,
            'inventory_source' => 'ecommerce',
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        // Submit selling units for ecommerce product: Base Unit (Single Bag 250g) + Additional Unit (Pack of 6) + Additional Unit (Carton of 24)
        $payload = [
            'inventory_source' => 'ecommerce',
            'name' => 'Artisan Coffee Beans',
            'category_id' => $this->category->id,
            'url_key' => 'artisan-coffee-beans',
            'price' => 2000,
            'stock' => 200,
            'pos_units' => json_encode([
                [
                    'unit_name' => 'Single Bag (250g)',
                    'unit_kind' => 'base',
                    'quantity_in_base_units' => 1.0,
                    'price' => 2000,
                    'online_label' => 'Single Bag',
                    'is_enabled_online' => true,
                    'image' => 'products/coffee-single.webp',
                ],
                [
                    'unit_name' => 'Pack of 6',
                    'unit_kind' => 'additional',
                    'quantity_in_base_units' => 6.0,
                    'price' => 11000,
                    'online_label' => 'Pack of 6 (Save ₦1,000)',
                    'is_enabled_online' => true,
                    'image' => 'products/coffee-pack.webp',
                ],
                [
                    'unit_name' => 'Carton of 24',
                    'unit_kind' => 'additional',
                    'quantity_in_base_units' => 24.0,
                    'price' => 40000,
                    'online_label' => 'Carton (24 Bags)',
                    'is_enabled_online' => true,
                    'image' => 'products/coffee-carton.webp',
                ]
            ]),
        ];

        $response = $this->actingAs($admin, 'web')->put(route('admin.products.update', $product->id), $payload);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $units = ProductPosUnit::where('product_id', $product->id)->orderBy('sort_order')->get();
        $this->assertCount(3, $units);

        $base = $units->firstWhere('unit_kind', 'base');
        $this->assertNotNull($base);
        $this->assertEquals('Single Bag (250g)', $base->unit_name);
        $this->assertEquals(1.0, (float) $base->quantity_in_base_units);
        $this->assertEquals(2000, (float) $base->synced_standard_price);

        $pack = $units->firstWhere('unit_name', 'Pack of 6');
        $this->assertNotNull($pack);
        $this->assertEquals(6.0, (float) $pack->quantity_in_base_units);
        $this->assertEquals(11000, (float) $pack->synced_standard_price);
        $this->assertEquals('products/coffee-pack.webp', $pack->image);

        $carton = $units->firstWhere('unit_name', 'Carton of 24');
        $this->assertNotNull($carton);
        $this->assertEquals(24.0, (float) $carton->quantity_in_base_units);
        $this->assertEquals(40000, (float) $carton->synced_standard_price);

        // Update removing Carton of 24
        $payload['pos_units'] = json_encode([
            [
                'id' => $base->id,
                'unit_name' => 'Single Bag (250g)',
                'unit_kind' => 'base',
                'quantity_in_base_units' => 1.0,
                'price' => 2000,
                'online_label' => 'Single Bag',
                'is_enabled_online' => true,
            ],
            [
                'id' => $pack->id,
                'unit_name' => 'Pack of 6',
                'unit_kind' => 'additional',
                'quantity_in_base_units' => 6.0,
                'price' => 11000,
                'online_label' => 'Pack of 6',
                'is_enabled_online' => true,
            ]
        ]);

        $response = $this->actingAs($admin, 'web')->put(route('admin.products.update', $product->id), $payload);
        $response->assertSessionHasNoErrors();

        $updatedUnits = ProductPosUnit::where('product_id', $product->id)->get();
        $this->assertCount(2, $updatedUnits);
        $this->assertNull(ProductPosUnit::find($carton->id));
    }

    public function test_order_deducts_and_restores_ecommerce_stock_using_selling_unit_multiplier(): void
    {
        $product = Product::create([
            'name' => 'Natural Mineral Water',
            'slug' => 'natural-mineral-water',
            'url_key' => 'natural-mineral-water',
            'price' => 200,
            'stock' => 100, // 100 bottles in warehouse
            'inventory_source' => 'ecommerce',
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        $cartonUnit = ProductPosUnit::create([
            'product_id' => $product->id,
            'unit_kind' => 'additional',
            'unit_name' => 'Carton of 12',
            'quantity_in_base_units' => 12.0,
            'synced_standard_price' => 2200,
            'online_label' => 'Carton of 12 Bottles',
            'is_enabled_online' => true,
        ]);

        $customer = \App\Models\Customer::create([
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
        ]);

        $order = Order::create([
            'tracking_number' => 'ORD-TEST-PACK-100',
            'customer_id' => $customer->id,
            'guest_email' => 'jane@example.com',
            'subtotal' => 4400,
            'total_amount' => 4400,
            'status' => 'pending',
        ]);

        // Customer bought 2 Cartons of 12 (Total = 24 base bottles)
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 2200,
            'inventory_source' => 'ecommerce',
            'unit_name' => 'Carton of 12',
            'quantity_in_base_units' => 24.0, // 2 cartons * 12 multiplier
            'standard_pos_price_snapshot' => 2200,
        ]);

        $inventoryService = app(\App\Services\InventoryService::class);
        $inventoryService->deductOrderInventory($order);

        $product->refresh();
        // 100 original - 24 base bottles = 76 bottles remaining
        $this->assertEquals(76, $product->stock);

        // Cancel order: restores 24 base bottles
        $inventoryService->restoreOrderInventory($order);
        $product->refresh();
        $this->assertEquals(100, $product->stock);
    }

    public function test_disabling_or_removing_all_selling_units_deletes_them_from_database(): void
    {
        $admin = User::factory()->create([
            'role' => RoleEnums::Administrator->value,
        ]);

        $product = Product::create([
            'name' => 'Premium Olive Oil',
            'slug' => 'premium-olive-oil',
            'url_key' => 'premium-olive-oil',
            'price' => 5000,
            'stock' => 50,
            'inventory_source' => 'ecommerce',
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        ProductPosUnit::create([
            'product_id' => $product->id,
            'unit_kind' => 'base',
            'unit_name' => '1 Bottle',
            'quantity_in_base_units' => 1.0,
            'synced_standard_price' => 5000,
        ]);

        ProductPosUnit::create([
            'product_id' => $product->id,
            'unit_kind' => 'additional',
            'unit_name' => 'Case of 6',
            'quantity_in_base_units' => 6.0,
            'synced_standard_price' => 28000,
        ]);

        $this->assertEquals(2, $product->posUnits()->count());

        // Admin disables packaging units (submits empty pos_units array or JSON)
        $payload = [
            'inventory_source' => 'ecommerce',
            'name' => 'Premium Olive Oil',
            'category_id' => $this->category->id,
            'url_key' => 'premium-olive-oil',
            'price' => 5000,
            'stock' => 50,
            'pos_units' => json_encode([]),
        ];

        $response = $this->actingAs($admin, 'web')->put(route('admin.products.update', $product->id), $payload);
        $response->assertSessionHasNoErrors();

        // Verify all units were deleted from the database
        $this->assertEquals(0, ProductPosUnit::where('product_id', $product->id)->count());
    }

    public function test_removing_variants_deletes_them_from_database_and_clears_variants_json(): void
    {
        $admin = User::factory()->create([
            'role' => RoleEnums::Administrator->value,
        ]);

        $product = Product::create([
            'name' => 'Cotton T-Shirt',
            'slug' => 'cotton-t-shirt',
            'url_key' => 'cotton-t-shirt',
            'price' => 2500,
            'stock' => 30,
            'inventory_source' => 'ecommerce',
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        // Add 2 variants
        $payload = [
            'inventory_source' => 'ecommerce',
            'name' => 'Cotton T-Shirt',
            'category_id' => $this->category->id,
            'url_key' => 'cotton-t-shirt',
            'price' => 2500,
            'stock' => 30,
            'variants' => json_encode([
                [
                    'name' => 'Red / Medium',
                    'price' => 2500,
                    'stock' => 10,
                    'inventory_source' => 'ecommerce',
                ],
                [
                    'name' => 'Blue / Large',
                    'price' => 2700,
                    'stock' => 20,
                    'inventory_source' => 'ecommerce',
                ]
            ]),
        ];

        $response = $this->actingAs($admin, 'web')->put(route('admin.products.update', $product->id), $payload);
        $response->assertSessionHasNoErrors();

        $this->assertEquals(2, $product->variantMappings()->count());
        $blueVariant = $product->variantMappings()->where('variant_label', 'Blue / Large')->first();
        $this->assertNotNull($blueVariant);

        // 1. Remove one variant (Red / Medium), keep Blue / Large
        $payload['variants'] = json_encode([
            [
                'id' => $blueVariant->id,
                'name' => 'Blue / Large',
                'price' => 2700,
                'stock' => 20,
                'inventory_source' => 'ecommerce',
            ]
        ]);

        $response = $this->actingAs($admin, 'web')->put(route('admin.products.update', $product->id), $payload);
        $response->assertSessionHasNoErrors();

        $this->assertEquals(1, $product->variantMappings()->count());
        $this->assertDatabaseMissing('product_variant_mappings', [
            'product_id' => $product->id,
            'variant_label' => 'Red / Medium',
        ]);

        // 2. Remove ALL variants
        $payload['variants'] = json_encode([]);

        $response = $this->actingAs($admin, 'web')->put(route('admin.products.update', $product->id), $payload);
        $response->assertSessionHasNoErrors();

        // Verify variant mappings table is empty for this product
        $this->assertEquals(0, $product->variantMappings()->count());

        // Verify products.variants JSON is empty array
        $product->refresh();
        $this->assertEmpty($product->variants);
    }
}

