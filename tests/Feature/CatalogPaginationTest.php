<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PosConnection;
use App\Models\Product;
use App\Models\SyncedPosItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => \App\Enums\RoleEnums::Administrator->value,
        ]);

        $this->category = Category::create([
            'name' => 'General',
            'slug' => 'general',
        ]);

        // Create 30 products
        for ($i = 1; $i <= 30; $i++) {
            Product::create([
                'category_id'      => $this->category->id,
                'name'             => "Product Item {$i}",
                'slug'             => "product-item-{$i}",
                'url_key'          => "product-item-{$i}",
                'sku'              => "SKU-{$i}",
                'stock'            => 10,
                'price'            => 1000 + $i,
                'status'           => true,
            ]);
        }

        // Create 30 synced POS items
        for ($i = 1; $i <= 30; $i++) {
            SyncedPosItem::create([
                'pos_item_id'            => 5000 + $i,
                'pos_item_name'          => "POS Item {$i}",
                'sku'                    => "POS-SKU-{$i}",
                'effective_availability' => 20,
                'standard_price'         => 2000 + $i,
                'is_active'              => true,
                'mapping_status'         => 'unmapped',
            ]);
        }
    }

    public function test_catalog_management_defaults_to_25_items(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.products.index'));

        $response->assertStatus(200);
        $products = $response->viewData('page')['props']['products'];
        $this->assertEquals(25, $products['per_page']);
        $this->assertCount(25, $products['data']);
        $this->assertEquals(30, $products['total']);
        $this->assertEquals(2, $products['last_page']);
    }

    public function test_catalog_management_can_load_50_and_100_items(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.products.index', ['per_page' => 50]));

        $response->assertStatus(200);
        $products = $response->viewData('page')['props']['products'];
        $this->assertEquals(50, $products['per_page']);
        $this->assertCount(30, $products['data']);
        $this->assertEquals(1, $products['last_page']);
    }

    public function test_catalog_management_can_load_all_items(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.products.index', ['per_page' => 'all']));

        $response->assertStatus(200);
        $products = $response->viewData('page')['props']['products'];
        $this->assertEquals(30, $products['per_page']);
        $this->assertCount(30, $products['data']);
        $this->assertEquals(1, $products['last_page']);
    }

    public function test_pos_catalog_integration_supports_per_page_options(): void
    {
        $response25 = $this->actingAs($this->admin)->get(route('admin.pos-integration.index', ['per_page' => 25]));
        $response25->assertStatus(200);
        $items25 = $response25->viewData('page')['props']['items'];
        $this->assertEquals(25, $items25['per_page']);
        $this->assertCount(25, $items25['data']);

        $responseAll = $this->actingAs($this->admin)->get(route('admin.pos-integration.index', ['per_page' => 'all']));
        $responseAll->assertStatus(200);
        $itemsAll = $responseAll->viewData('page')['props']['items'];
        $this->assertEquals(30, $itemsAll['per_page']);
        $this->assertCount(30, $itemsAll['data']);
        $this->assertEquals(1, $itemsAll['last_page']);
    }
}

