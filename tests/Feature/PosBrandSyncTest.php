<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\PosConnection;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\SyncedPosItem;
use App\Models\User;
use App\Services\PosIntegration\PosCatalogService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PosBrandSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected PosConnection $posConnection;
    protected string $webhookSecret = 'pos-secret-token-999';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@skynet.test',
            'password' => bcrypt('password'),
            'role' => 'super administrator',
            'status' => 'enable',
        ]);

        $this->posConnection = PosConnection::create([
            'pos_base_url' => 'http://pos.test',
            'pos_branch_id' => 1,
            'pos_branch_name' => 'Main Branch',
            'client_id' => 'branch-1',
            'encrypted_api_secret' => Crypt::encryptString($this->webhookSecret),
            'is_connected' => true,
            'connected_at' => now(),
        ]);
    }

    public function test_pos_sync_with_missing_brand_fields_still_imports_products_cleanly(): void
    {
        Http::fake([
            '*brands*'   => Http::response(['message' => 'Not Found'], 404),
            '*catalog*'  => Http::response([
                'data' => [
                    [
                        'id'                     => 101,
                        'name'                   => 'Generic USB Cable',
                        'category_name'          => 'Cables',
                        'standard_price'         => 2500,
                        'effective_availability' => 20,
                        'is_active'              => true,
                        'units'                  => [],
                    ],
                ],
                'meta' => ['last_page' => 1],
            ], 200),
            '*items*'    => Http::response([
                'data' => [
                    [
                        'pos_item_id'            => 101,
                        'item_name'              => 'Generic USB Cable',
                        'category_name'          => 'Cables',
                        'standard_price'         => 2500,
                        'effective_availability' => 20,
                        'is_active'              => true,
                        'units'                  => [],
                    ],
                ],
                'meta' => ['last_page' => 1],
            ], 200),
            '*mappings*' => Http::response(['status' => true], 200),
        ]);

        $catalogService = app(PosCatalogService::class);
        $syncResult = $catalogService->syncCatalog(true);

        $this->assertTrue($syncResult['status'], $syncResult['message'] ?? 'sync failed');
        $synced = SyncedPosItem::where('pos_item_id', 101)->first();
        $this->assertNotNull($synced);
        $this->assertNull($synced->pos_brand_id);
        $this->assertNull($synced->pos_brand_name);

        $product = $catalogService->importAsSimpleProduct(101);
        $this->assertEquals('Generic USB Cable', $product->name);
        $this->assertNull($product->brand_id);
    }

    public function test_importing_pos_item_with_unknown_brand_auto_creates_brand_with_pos_brand_id(): void
    {
        SyncedPosItem::create([
            'pos_item_id' => 202,
            'pos_item_name' => 'Samsung Galaxy S25 Ultra',
            'pos_category_name' => 'Smartphones',
            'pos_brand_id' => 55,
            'pos_brand_name' => 'Samsung',
            'standard_price' => 1950000,
            'effective_availability' => 6,
            'is_active_in_pos' => true,
            'mapping_status' => 'unmapped',
        ]);

        Http::fake([
            'pos.test/api/commerce/v1/mappings*' => Http::response(['status' => true], 200),
        ]);

        $catalogService = app(PosCatalogService::class);
        $product = $catalogService->importAsSimpleProduct(202);

        $this->assertNotNull($product->brand_id);
        $brand = Brand::find($product->brand_id);
        $this->assertNotNull($brand);
        $this->assertEquals('Samsung', $brand->name);
        $this->assertEquals('samsung', $brand->slug);
        $this->assertEquals(55, $brand->pos_brand_id);
    }

    public function test_importing_pos_item_matches_existing_ecommerce_brand_case_insensitively_and_backfills_pos_brand_id(): void
    {
        // Pre-existing eCommerce-only brand (pos_brand_id is null)
        $existingBrand = Brand::create([
            'name' => 'SAMSUNG',
            'slug' => 'samsung',
            'pos_brand_id' => null,
            'status' => true,
        ]);

        SyncedPosItem::create([
            'pos_item_id' => 203,
            'pos_item_name' => 'Samsung Galaxy Buds3 Pro',
            'pos_category_name' => 'Audio',
            'pos_brand_id' => 77,
            'pos_brand_name' => 'samsung',
            'standard_price' => 260000,
            'effective_availability' => 15,
            'is_active_in_pos' => true,
            'mapping_status' => 'unmapped',
        ]);

        Http::fake([
            'pos.test/api/commerce/v1/mappings*' => Http::response(['status' => true], 200),
        ]);

        $catalogService = app(PosCatalogService::class);
        $product = $catalogService->importAsSimpleProduct(203);

        // No duplicate brand created
        $this->assertEquals(1, Brand::count());
        $this->assertEquals($existingBrand->id, $product->brand_id);
        $this->assertEquals(77, $existingBrand->fresh()->pos_brand_id);
    }

    public function test_bulk_importing_50_items_sharing_same_pos_brand_creates_only_one_brand_row(): void
    {
        $posIds = [];
        for ($i = 1; $i <= 50; $i++) {
            $posId = 1000 + $i;
            $posIds[] = $posId;
            SyncedPosItem::create([
                'pos_item_id' => $posId,
                'pos_item_name' => "Anker Charger Model #{$i}",
                'pos_category_name' => 'Chargers',
                'pos_brand_id' => 88,
                'pos_brand_name' => 'Anker',
                'standard_price' => 25000,
                'effective_availability' => 10,
                'is_active_in_pos' => true,
                'mapping_status' => 'unmapped',
            ]);
        }

        Http::fake([
            'pos.test/api/commerce/v1/mappings*' => Http::response(['status' => true], 200),
        ]);

        $catalogService = app(PosCatalogService::class);
        $result = $catalogService->bulkImportSimpleProducts($posIds);

        $this->assertEquals(50, $result['total_imported']);
        $this->assertEquals(1, Brand::where('name', 'Anker')->count());

        $anker = Brand::where('name', 'Anker')->first();
        $this->assertEquals(88, $anker->pos_brand_id);
        $this->assertEquals(50, Product::where('brand_id', $anker->id)->count());
    }

    public function test_webhook_brand_updated_renames_linked_brand_preserving_pos_brand_id(): void
    {
        $brand = Brand::create([
            'name' => 'HP',
            'slug' => 'hp',
            'pos_brand_id' => 42,
            'status' => true,
        ]);

        $response = $this->postJson('/api/webhooks/pos-inventory', [
            'event' => 'brand.updated',
            'branch_id' => 1,
            'brand' => [
                'pos_brand_id' => 42,
                'name' => 'Hewlett-Packard',
                'slug' => 'hewlett-packard',
                'description' => 'Enterprise & Consumer Computing',
                'is_active' => true,
            ],
        ], [
            'Authorization' => 'Bearer ' . $this->webhookSecret,
            'X-Skynet-Event' => 'brand.updated',
            'X-Branch-Id' => '1',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'event' => 'brand.updated']);

        $brand->refresh();
        $this->assertEquals('Hewlett-Packard', $brand->name);
        $this->assertEquals(42, $brand->pos_brand_id);
    }

    public function test_webhook_brand_deleted_clears_pos_brand_id_but_does_not_delete_ecommerce_brand_or_product_assignment(): void
    {
        $brand = Brand::create([
            'name' => 'Oraimo',
            'slug' => 'oraimo',
            'pos_brand_id' => 91,
            'status' => true,
        ]);

        $category = Category::create(['name' => 'Powerbanks', 'slug' => 'powerbanks']);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Oraimo Traveler 20000mAh',
            'slug' => 'oraimo-traveler-20000',
            'url_key' => 'oraimo-traveler-20000',
            'price' => 32000,
            'stock' => 14,
            'status' => true,
        ]);

        SyncedPosItem::create([
            'pos_item_id' => 501,
            'pos_item_name' => 'Oraimo Traveler 20000mAh',
            'pos_brand_id' => 91,
            'pos_brand_name' => 'Oraimo',
            'standard_price' => 32000,
            'effective_availability' => 14,
            'is_active_in_pos' => true,
        ]);

        $response = $this->postJson('/api/webhooks/pos-inventory', [
            'event' => 'brand.deleted',
            'branch_id' => 1,
            'pos_brand_id' => 91,
        ], [
            'Authorization' => 'Bearer ' . $this->webhookSecret,
            'X-Skynet-Event' => 'brand.deleted',
            'X-Branch-Id' => '1',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'event' => 'brand.deleted']);

        // Brand still exists, only pos_brand_id is nulled
        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'Oraimo',
            'pos_brand_id' => null,
        ]);

        // Product still retains its brand_id
        $this->assertEquals($brand->id, $product->fresh()->brand_id);

        // SyncedPosItem had its pos_brand_id cleared
        $this->assertNull(SyncedPosItem::where('pos_item_id', 501)->first()->pos_brand_id);
    }

    public function test_variant_consolidation_inherits_brand_from_absorbed_standalone_product(): void
    {
        $brand = Brand::create([
            'name' => 'Nike',
            'pos_brand_id' => 300,
            'status' => true,
        ]);

        $category = Category::create(['name' => 'Footwear', 'slug' => 'footwear']);

        SyncedPosItem::create([
            'pos_item_id' => 701,
            'pos_item_name' => 'Nike Air Force 1 - Size 42',
            'pos_brand_id' => 300,
            'pos_brand_name' => 'Nike',
            'standard_price' => 95000,
            'effective_availability' => 4,
            'is_active_in_pos' => true,
            'mapping_status' => 'mapped_simple',
        ]);

        // Standalone product with brand_id set
        $standalone = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'pos_item_id' => 701,
            'inventory_source' => 'pos',
            'name' => 'Nike Air Force 1 - Size 42',
            'slug' => 'nike-af1-42',
            'url_key' => 'nike-af1-42',
            'price' => 95000,
            'stock' => 4,
            'status' => true,
        ]);

        // Parent product initially has no brand_id
        $parent = Product::create([
            'category_id' => $category->id,
            'brand_id' => null,
            'inventory_source' => 'ecommerce',
            'name' => 'Nike Air Force 1',
            'slug' => 'nike-air-force-1',
            'url_key' => 'nike-air-force-1',
            'price' => 95000,
            'stock' => 4,
            'status' => true,
        ]);

        Http::fake([
            'pos.test/api/commerce/v1/mappings*' => Http::response(['status' => true], 200),
        ]);

        $response = $this->actingAs($this->admin, 'web')->post(route('admin.products.update', $parent->id), [
            '_method' => 'PUT',
            'inventory_source' => 'ecommerce',
            'name' => 'Nike Air Force 1',
            'category_id' => $category->id,
            'brand_id' => '', // intentionally empty so it inherits from absorbed standalone
            'url_key' => 'nike-air-force-1',
            'price' => 95000,
            'stock' => 4,
            'status' => true,
            'is_new' => false,
            'is_featured' => false,
            'visible_individually' => true,
            'show_stock_level' => true,
            'variants' => json_encode([
                [
                    'name' => 'Size 42',
                    'price' => 95000,
                    'stock' => 4,
                    'inventory_source' => 'pos',
                    'pos_item_id' => 701,
                    'absorb_standalone' => true,
                ],
            ]),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('products', ['id' => $standalone->id]);
        $this->assertEquals($brand->id, $parent->fresh()->brand_id);
    }

    public function test_pos_brand_sync_overwrite_setting_controls_whether_existing_product_brand_is_overwritten(): void
    {
        $brandA = Brand::create(['name' => 'Brand Alpha', 'pos_brand_id' => 10, 'status' => true]);
        $brandB = Brand::create(['name' => 'Brand Beta', 'pos_brand_id' => 20, 'status' => true]);
        $category = Category::create(['name' => 'Gadgets', 'slug' => 'gadgets']);

        SyncedPosItem::create([
            'pos_item_id' => 801,
            'pos_item_name' => 'Smart Watch X',
            'pos_brand_id' => 10,
            'pos_brand_name' => 'Brand Alpha',
            'standard_price' => 50000,
            'effective_availability' => 5,
            'is_active_in_pos' => true,
            'mapping_status' => 'mapped_simple',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brandA->id,
            'pos_item_id' => 801,
            'inventory_source' => 'pos',
            'name' => 'Smart Watch X',
            'slug' => 'smart-watch-x',
            'url_key' => 'smart-watch-x',
            'price' => 50000,
            'stock' => 5,
            'status' => true,
        ]);

        // 1. Default: pos_brand_sync_overwrite = 0 -> webhook item.updated changing POS brand to Brand Beta does NOT overwrite product.brand_id
        StoreSetting::set('pos_brand_sync_overwrite', '0');

        $this->postJson('/api/webhooks/pos-inventory', [
            'event' => 'item.updated',
            'branch_id' => 1,
            'items' => [
                [
                    'pos_item_id' => 801,
                    'pos_brand_id' => 20,
                    'pos_brand_name' => 'Brand Beta',
                ],
            ],
        ], [
            'Authorization' => 'Bearer ' . $this->webhookSecret,
            'X-Skynet-Event' => 'item.updated',
            'X-Branch-Id' => '1',
        ])->assertStatus(200);

        $this->assertEquals($brandA->id, $product->fresh()->brand_id);

        // 2. Enable pos_brand_sync_overwrite = 1 -> webhook item.updated DOES update product.brand_id to Brand Beta
        StoreSetting::set('pos_brand_sync_overwrite', '1');

        $this->postJson('/api/webhooks/pos-inventory', [
            'event' => 'item.updated',
            'branch_id' => 1,
            'items' => [
                [
                    'pos_item_id' => 801,
                    'pos_brand_id' => 20,
                    'pos_brand_name' => 'Brand Beta',
                ],
            ],
        ], [
            'Authorization' => 'Bearer ' . $this->webhookSecret,
            'X-Skynet-Event' => 'item.updated',
            'X-Branch-Id' => '1',
        ])->assertStatus(200);

        $this->assertEquals($brandB->id, $product->fresh()->brand_id);
    }

    public function test_backfill_command_populates_missing_product_brands_from_synced_pos_items(): void
    {
        $category = Category::create(['name' => 'Home Appliances', 'slug' => 'home-appliances']);

        SyncedPosItem::create([
            'pos_item_id' => 901,
            'pos_item_name' => 'Binatone Standing Fan 18 Inch',
            'pos_brand_id' => 65,
            'pos_brand_name' => 'Binatone',
            'standard_price' => 48000,
            'effective_availability' => 9,
            'is_active_in_pos' => true,
            'mapping_status' => 'mapped_simple',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => null,
            'pos_item_id' => 901,
            'inventory_source' => 'pos',
            'name' => 'Binatone Standing Fan 18 Inch',
            'slug' => 'binatone-fan-18',
            'url_key' => 'binatone-fan-18',
            'price' => 48000,
            'stock' => 9,
            'status' => true,
        ]);

        $this->artisan('brands:backfill-from-pos')
            ->assertExitCode(0);

        $product->refresh();
        $this->assertNotNull($product->brand_id);
        $this->assertEquals('Binatone', $product->brand->name);
        $this->assertEquals(65, $product->brand->pos_brand_id);
    }
}