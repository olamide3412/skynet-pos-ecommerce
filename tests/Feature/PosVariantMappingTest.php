<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PosConnection;
use App\Models\Product;
use App\Models\ProductVariantMapping;
use App\Models\SyncedPosItem;
use App\Models\User;
use App\Services\PosIntegration\PosApiClient;
use App\Services\PosIntegration\PosCatalogService;
use App\Services\PosIntegration\PosReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PosVariantMappingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $category;
    protected string $secret = 'test_webhook_secret_key_12345';
    protected int $branchId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'role'  => 'administrator',
        ]);

        $this->category = Category::create([
            'name' => 'Clothing',
            'slug' => 'clothing',
        ]);

        $conn = new PosConnection();
        $conn->pos_base_url = 'https://pos.skynetdigital.com/api/commerce/v1';
        $conn->client_id = 'test_client_id';
        $conn->api_secret = $this->secret;
        $conn->pos_branch_id = $this->branchId;
        $conn->pos_branch_name = 'Test POS Branch';
        $conn->is_connected = true;
        $conn->save();

        Http::fake([
            '*/mappings/register'   => Http::response(['status' => true], 200),
            '*/mappings/unregister' => Http::response(['status' => true], 200),
            '*/mappings/reconcile'  => Http::response(['status' => true, 'count' => 2], 200),
            '*/reservations'        => Http::response(['status' => true, 'data' => ['id' => 1]], 200),
        ]);
    }

    public function test_admin_can_save_product_with_pos_variant_mappings(): void
    {
        $posItemSmall = SyncedPosItem::create([
            'pos_item_id'            => 101,
            'pos_item_name'          => 'Nike Shirt - Small',
            'sku'                    => 'NIKE-S',
            'barcode'                => '111111',
            'is_active'              => true,
            'standard_price'         => 12000.00,
            'buying_price'           => 8000.00,
            'effective_availability' => 15,
            'base_unit_name'         => 'Piece',
            'mapping_status'         => 'unmapped',
        ]);

        $posItemMed = SyncedPosItem::create([
            'pos_item_id'            => 102,
            'pos_item_name'          => 'Nike Shirt - Medium',
            'sku'                    => 'NIKE-M',
            'barcode'                => '222222',
            'is_active'              => true,
            'standard_price'         => 12000.00,
            'buying_price'           => 8000.00,
            'effective_availability' => 20,
            'base_unit_name'         => 'Piece',
            'mapping_status'         => 'unmapped',
        ]);

        $variants = [
            [
                'name'             => 'Small',
                'inventory_source' => 'pos',
                'pos_item_id'      => 101,
                'price'            => 12000.00,
                'stock'            => 15,
            ],
            [
                'name'             => 'Medium',
                'inventory_source' => 'pos',
                'pos_item_id'      => 102,
                'price'            => 12000.00,
                'stock'            => 20,
            ],
            [
                'name'             => 'Large (Online Only)',
                'inventory_source' => 'ecommerce',
                'pos_item_id'      => null,
                'price'            => 11500.00,
                'stock'            => 5,
            ],
        ];

        $payload = [
            'name'                 => 'Nike Dri-FIT Shirt',
            'url_key'              => 'nike-dri-fit-shirt',
            'category_id'          => $this->category->id,
            'price'                => 12000.00,
            'stock'                => 40,
            'inventory_source'     => 'ecommerce',
            'status'               => 1,
            'variants'             => json_encode($variants),
            'visible_individually' => 1,
            'show_stock_level'     => 1,
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.products.store'), $payload);
        $response->assertSessionHas('success');

        $product = Product::where('url_key', 'nike-dri-fit-shirt')->first();
        $this->assertNotNull($product);

        // Check variant mappings in database
        $this->assertDatabaseHas('product_variant_mappings', [
            'product_id'       => $product->id,
            'variant_label'    => 'Small',
            'pos_item_id'      => 101,
            'inventory_source' => 'pos',
            'local_stock'      => 15,
        ]);

        $this->assertDatabaseHas('product_variant_mappings', [
            'product_id'       => $product->id,
            'variant_label'    => 'Medium',
            'pos_item_id'      => 102,
            'inventory_source' => 'pos',
            'local_stock'      => 20,
        ]);

        $this->assertDatabaseHas('product_variant_mappings', [
            'product_id'       => $product->id,
            'variant_label'    => 'Large (Online Only)',
            'pos_item_id'      => null,
            'inventory_source' => 'ecommerce',
            'local_stock'      => 5,
        ]);

        // Check mapping status on SyncedPosItems
        $this->assertEquals('mapped_variant', $posItemSmall->fresh()->mapping_status);
        $this->assertEquals('mapped_variant', $posItemMed->fresh()->mapping_status);
    }

    public function test_variant_pos_item_unmapped_when_variant_removed(): void
    {
        $posItemSmall = SyncedPosItem::create([
            'pos_item_id'            => 201,
            'pos_item_name'          => 'Cap - Small',
            'sku'                    => 'CAP-S',
            'is_active'              => true,
            'standard_price'         => 3000.00,
            'effective_availability' => 10,
            'mapping_status'         => 'unmapped',
        ]);

        $posItemLarge = SyncedPosItem::create([
            'pos_item_id'            => 202,
            'pos_item_name'          => 'Cap - Large',
            'sku'                    => 'CAP-L',
            'is_active'              => true,
            'standard_price'         => 3000.00,
            'effective_availability' => 10,
            'mapping_status'         => 'unmapped',
        ]);

        $product = Product::create([
            'name'             => 'Baseball Cap',
            'slug'             => 'baseball-cap',
            'url_key'          => 'baseball-cap',
            'category_id'      => $this->category->id,
            'price'            => 3000.00,
            'stock'            => 20,
            'inventory_source' => 'ecommerce',
            'status'           => true,
            'pos_sync_status'  => 'synced',
        ]);

        // Initial mapping with both variants
        $variants = [
            ['name' => 'Small', 'inventory_source' => 'pos', 'pos_item_id' => 201, 'price' => 3000, 'stock' => 10],
            ['name' => 'Large', 'inventory_source' => 'pos', 'pos_item_id' => 202, 'price' => 3000, 'stock' => 10],
        ];

        $this->actingAs($this->admin)->put(route('admin.products.update', $product->id), [
            'name'                 => 'Baseball Cap',
            'url_key'              => 'baseball-cap',
            'category_id'          => $this->category->id,
            'price'                => 3000.00,
            'stock'                => 20,
            'inventory_source'     => 'ecommerce',
            'status'               => 1,
            'variants'             => json_encode($variants),
            'visible_individually' => 1,
            'show_stock_level'     => 1,
        ]);

        $this->assertEquals('mapped_variant', $posItemSmall->fresh()->mapping_status);
        $this->assertEquals('mapped_variant', $posItemLarge->fresh()->mapping_status);

        // Update product removing Large variant
        $onlySmall = [
            ['name' => 'Small', 'inventory_source' => 'pos', 'pos_item_id' => 201, 'price' => 3000, 'stock' => 10],
        ];

        $this->actingAs($this->admin)->put(route('admin.products.update', $product->id), [
            'name'                 => 'Baseball Cap',
            'url_key'              => 'baseball-cap',
            'category_id'          => $this->category->id,
            'price'                => 3000.00,
            'stock'                => 10,
            'inventory_source'     => 'ecommerce',
            'status'               => 1,
            'variants'             => json_encode($onlySmall),
            'visible_individually' => 1,
            'show_stock_level'     => 1,
        ]);

        $this->assertEquals('mapped_variant', $posItemSmall->fresh()->mapping_status);
        $this->assertEquals('unmapped', $posItemLarge->fresh()->mapping_status);
    }

    public function test_pos_webhook_updates_variant_stock(): void
    {
        $posItem = SyncedPosItem::create([
            'pos_item_id'            => 301,
            'pos_item_name'          => 'Running Shoes - 42',
            'sku'                    => 'SHOE-42',
            'barcode'                => '777888999',
            'is_active'              => true,
            'standard_price'         => 25000.00,
            'effective_availability' => 10,
            'mapping_status'         => 'mapped_variant',
        ]);

        $product = Product::create([
            'name'             => 'Pro Running Shoes',
            'slug'             => 'pro-running-shoes',
            'url_key'          => 'pro-running-shoes',
            'category_id'      => $this->category->id,
            'price'            => 25000.00,
            'stock'            => 10,
            'inventory_source' => 'ecommerce',
            'status'           => true,
            'pos_sync_status'  => 'synced',
            'variants'         => [
                ['name' => 'Size 42', 'inventory_source' => 'pos', 'pos_item_id' => 301, 'stock' => 10, 'price' => 25000],
            ],
        ]);

        $variantMapping = ProductVariantMapping::create([
            'product_id'       => $product->id,
            'variant_key'      => 'size_42',
            'variant_label'    => 'Size 42',
            'pos_item_id'      => 301,
            'inventory_source' => 'pos',
            'local_stock'      => 10,
            'local_price'      => 25000.00,
            'is_active'        => true,
        ]);

        $payload = [
            'event'     => 'inventory.updated',
            'branch_id' => $this->branchId,
            'data'      => [
                'pos_item_id'    => 301,
                'available_qty'  => 3,
                'price'          => 27000.00,
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
                'CONTENT_TYPE'            => 'application/json',
                'HTTP_ACCEPT'             => 'application/json',
                'HTTP_X_SKYNET_EVENT'     => 'inventory.updated',
                'HTTP_X_BRANCH_ID'        => (string) $this->branchId,
                'HTTP_AUTHORIZATION'      => 'Bearer ' . $this->secret,
                'HTTP_X_COMMERCE_KEY'     => $this->secret,
                'HTTP_X_SKYNET_SIGNATURE' => $signature,
            ],
            $jsonPayload
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'stock'   => 3,
                'price'   => 27000,
            ]);

        // Verify variant mapping was updated
        $this->assertEquals(3, $variantMapping->fresh()->local_stock);
        $this->assertEquals(27000, $variantMapping->fresh()->local_price);

        // Verify SyncedPosItem was updated
        $this->assertEquals(3, $posItem->fresh()->effective_availability);
        $this->assertEquals(27000, $posItem->fresh()->standard_price);

        // Verify parent product variants JSON column was updated
        $updatedProduct = $product->fresh();
        $this->assertEquals(3, $updatedProduct->variants[0]['stock']);
        $this->assertEquals(27000, $updatedProduct->variants[0]['price']);
    }

    public function test_reconcile_all_mappings_includes_active_variants(): void
    {
        $posItem = SyncedPosItem::create([
            'pos_item_id'            => 401,
            'pos_item_name'          => 'Variant Item 401',
            'sku'                    => 'VAR-401',
            'is_active'              => true,
            'standard_price'         => 5000.00,
            'effective_availability' => 8,
            'mapping_status'         => 'mapped_variant',
        ]);

        $product = Product::create([
            'name'             => 'Multi Variant Shirt',
            'slug'             => 'multi-variant-shirt',
            'url_key'          => 'multi-variant-shirt',
            'category_id'      => $this->category->id,
            'price'            => 5000.00,
            'stock'            => 8,
            'inventory_source' => 'ecommerce',
            'status'           => true,
            'pos_sync_status'  => 'synced',
        ]);

        ProductVariantMapping::create([
            'product_id'       => $product->id,
            'variant_key'      => 'size_s',
            'variant_label'    => 'Size S',
            'pos_item_id'      => 401,
            'inventory_source' => 'pos',
            'local_stock'      => 8,
            'local_price'      => 5000.00,
            'is_active'        => true,
        ]);

        $catalogService = app(PosCatalogService::class);
        $result = $catalogService->reconcileAllMappings();

        $this->assertTrue($result['status']);
        $this->assertEquals('mapped_variant', $posItem->fresh()->mapping_status);
    }

    public function test_customer_can_add_variant_to_cart_and_reserve(): void
    {
        $posItem = SyncedPosItem::create([
            'pos_item_id'            => 501,
            'pos_item_name'          => 'Hoodie - XL',
            'sku'                    => 'HD-XL',
            'is_active'              => true,
            'standard_price'         => 18000.00,
            'buying_price'           => 12000.00,
            'effective_availability' => 5,
            'base_unit_name'         => 'Piece',
            'mapping_status'         => 'mapped_variant',
        ]);

        $product = Product::create([
            'name'             => 'Streetwear Hoodie',
            'slug'             => 'streetwear-hoodie',
            'url_key'          => 'streetwear-hoodie',
            'category_id'      => $this->category->id,
            'price'            => 18000.00,
            'stock'            => 5,
            'inventory_source' => 'ecommerce',
            'status'           => true,
            'pos_sync_status'  => 'synced',
        ]);

        $variantMapping = ProductVariantMapping::create([
            'product_id'       => $product->id,
            'variant_key'      => 'size_xl',
            'variant_label'    => 'Size XL',
            'pos_item_id'      => 501,
            'inventory_source' => 'pos',
            'local_stock'      => 5,
            'local_price'      => 18000.00,
            'is_active'        => true,
        ]);

        // Add to cart
        $response = $this->post(route('cart.store'), [
            'product_id'                 => $product->id,
            'quantity'                   => 2,
            'product_variant_mapping_id' => $variantMapping->id,
            'variant'                    => 'Size XL',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('cart_items', [
            'product_id'                 => $product->id,
            'product_variant_mapping_id' => $variantMapping->id,
            'quantity'                   => 2,
            'price'                      => 18000.00,
        ]);

        $cart = \App\Models\Cart::first();
        $this->assertNotNull($cart);

        // Reserve for cart
        $reservationService = app(PosReservationService::class);
        $resResult = $reservationService->reserveForCart($cart);

        $this->assertTrue($resResult['status']);
        $this->assertTrue($resResult['has_pos_items']);
        $this->assertEquals(1, $resResult['items_reserved']);
    }

    public function test_active_standalone_pos_product_can_be_consolidated_into_variant_of_another_pos_product(): void
    {
        $posItem400g = SyncedPosItem::create([
            'pos_item_id'            => 601,
            'pos_item_name'          => 'Peak Milk 400g',
            'sku'                    => 'PEAK-400',
            'is_active'              => true,
            'standard_price'         => 4500.00,
            'effective_availability' => 20,
            'mapping_status'         => 'mapped_simple',
        ]);

        $posItem900g = SyncedPosItem::create([
            'pos_item_id'            => 602,
            'pos_item_name'          => 'Peak Milk 900g',
            'sku'                    => 'PEAK-900',
            'is_active'              => true,
            'standard_price'         => 9500.00,
            'effective_availability' => 15,
            'mapping_status'         => 'mapped_simple',
        ]);

        // Both are initially active standalone POS products on the storefront
        $mainPosProduct = Product::create([
            'name'             => 'Peak Milk',
            'slug'             => 'peak-milk',
            'url_key'          => 'peak-milk',
            'category_id'      => $this->category->id,
            'price'            => 4500.00,
            'stock'            => 20,
            'inventory_source' => 'pos',
            'pos_item_id'      => 601,
            'status'           => true,
            'pos_sync_status'  => 'synced',
        ]);

        $standalone900gProduct = Product::create([
            'name'             => 'Peak Milk 900g',
            'slug'             => 'peak-milk-900g',
            'url_key'          => 'peak-milk-900g',
            'category_id'      => $this->category->id,
            'price'            => 9500.00,
            'stock'            => 15,
            'inventory_source' => 'pos',
            'pos_item_id'      => 602,
            'status'           => true,
            'pos_sync_status'  => 'synced',
        ]);

        // Admin maps 601 (400g) and 602 (900g) as variants of Peak Milk, consolidating the standalone 900g product
        $variants = [
            [
                'name'              => '400g Tin',
                'inventory_source'  => 'pos',
                'pos_item_id'       => 601,
                'price'             => 4500,
                'stock'             => 20,
                'absorb_standalone' => false,
            ],
            [
                'name'              => '900g Tin',
                'inventory_source'  => 'pos',
                'pos_item_id'       => 602,
                'price'             => 9500,
                'stock'             => 15,
                'absorb_standalone' => true,
            ],
        ];

        $this->actingAs($this->admin)->put(route('admin.products.update', $mainPosProduct->id), [
            'name'                 => 'Peak Milk',
            'url_key'              => 'peak-milk',
            'category_id'          => $this->category->id,
            'price'                => 4500.00,
            'stock'                => 20,
            'inventory_source'     => 'pos',
            'pos_item_id'          => 601,
            'status'               => 1,
            'variants'             => json_encode($variants),
            'visible_individually' => 1,
            'show_stock_level'     => 1,
        ])->assertRedirect();

        // Duplicate standalone 900g product (with no orders) is cleanly removed
        $this->assertDatabaseMissing('products', [
            'id' => $standalone900gProduct->id,
        ]);

        // Both variants remain 100% POS-linked (never converted to ecommerce)
        $this->assertDatabaseHas('product_variant_mappings', [
            'product_id'       => $mainPosProduct->id,
            'variant_label'    => '900g Tin',
            'inventory_source' => 'pos',
            'pos_item_id'      => 602,
            'local_stock'      => 15,
            'local_price'      => 9500.00,
        ]);

        // POS item 602 mapping_status is now mapped_variant
        $this->assertEquals('mapped_variant', $posItem900g->fresh()->mapping_status);

        // Parent total stock reflects both POS variants (20 + 15 = 35)
        $this->assertEquals(35, $mainPosProduct->fresh()->stock);

        // Catalog sync for 602 updates the variant stock & parent total stock seamlessly
        $catalogService = app(PosCatalogService::class);
        $catalogService->upsertSyncedItem([
            'id'                     => 602,
            'name'                   => 'Peak Milk 900g',
            'sku'                    => 'PEAK-900',
            'is_active'              => true,
            'standard_price'         => 9800.00,
            'effective_availability' => 0,
        ]);

        $this->assertDatabaseHas('product_variant_mappings', [
            'product_id'    => $mainPosProduct->id,
            'pos_item_id'   => 602,
            'local_stock'   => 0,
            'local_price'   => 9800.00,
        ]);
        $this->assertEquals(20, $mainPosProduct->fresh()->stock);
    }
}
