<?php

namespace Tests\Feature;

use App\Enums\RoleEnums;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPosUnit;
use App\Models\SyncedPosItem;
use App\Models\User;
use App\Services\PosIntegration\PosApiClient;
use App\Services\PosIntegration\PosCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBulkMoveToEcommerceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => RoleEnums::Administrator->value,
        ]);

        $this->category = Category::create([
            'name' => 'General',
            'slug' => 'general',
        ]);
    }

    public function test_can_bulk_move_pos_products_to_ecommerce_only(): void
    {
        // Mock PosApiClient and PosCatalogService to prevent external network calls
        $mockApiClient = $this->createMock(PosApiClient::class);
        $mockApiClient->expects($this->once())
            ->method('unregisterMappings')
            ->with($this->callback(function ($ids) {
                return in_array(2001, $ids) && in_array(2002, $ids);
            }))
            ->willReturn(true);
        $this->app->instance(PosApiClient::class, $mockApiClient);

        $mockCatalog = $this->createMock(PosCatalogService::class);
        $mockCatalog->expects($this->once())
            ->method('reconcileAllMappings')
            ->willReturn(['success' => true]);
        $this->app->instance(PosCatalogService::class, $mockCatalog);

        // Create 2 POS Synced items
        SyncedPosItem::create([
            'pos_item_id' => 2001,
            'pos_item_name' => 'POS Item 1',
            'standard_price' => 1500,
            'buying_price' => 1000,
            'mapping_status' => 'mapped_simple',
        ]);

        SyncedPosItem::create([
            'pos_item_id' => 2002,
            'pos_item_name' => 'POS Item 2',
            'standard_price' => 3000,
            'buying_price' => 2200,
            'mapping_status' => 'mapped_simple',
        ]);

        // Create 2 POS products
        $posProduct1 = Product::create([
            'name' => 'POS Product 1',
            'slug' => 'pos-product-1',
            'url_key' => 'pos-product-1',
            'price' => 1500,
            'cost_price' => 1000,
            'stock' => 50,
            'inventory_source' => 'pos',
            'pos_item_id' => 2001,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        // Create a unit conversion for POS Product 1
        ProductPosUnit::create([
            'product_id' => $posProduct1->id,
            'pos_item_unit_id' => 10,
            'unit_name' => 'Carton',
            'quantity_in_base_units' => 12,
            'synced_standard_price' => 18000,
        ]);

        $posProduct2 = Product::create([
            'name' => 'POS Product 2',
            'slug' => 'pos-product-2',
            'url_key' => 'pos-product-2',
            'price' => 3000,
            'cost_price' => 2200,
            'stock' => 25,
            'inventory_source' => 'pos',
            'pos_item_id' => 2002,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        // Create 1 standard Ecommerce product (should be untouched)
        $ecomProduct = Product::create([
            'name' => 'Pure Ecom Product',
            'slug' => 'pure-ecom',
            'url_key' => 'pure-ecom',
            'price' => 500,
            'cost_price' => 300,
            'stock' => 100,
            'inventory_source' => 'ecommerce',
            'pos_item_id' => null,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        // Perform bulk move
        $response = $this->actingAs($this->admin)
            ->post(route('admin.products.bulk-action'), [
                'action' => 'move_to_ecommerce',
                'ids' => [$posProduct1->id, $posProduct2->id, $ecomProduct->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check posProduct1
        $posProduct1->refresh();
        $this->assertEquals('ecommerce', $posProduct1->inventory_source);
        $this->assertNull($posProduct1->pos_item_id);
        $this->assertEquals(1500, $posProduct1->price);
        $this->assertEquals(1000, $posProduct1->cost_price);
        $this->assertEquals(50, $posProduct1->stock);
        $this->assertCount(0, $posProduct1->posUnits);

        // Check posProduct2
        $posProduct2->refresh();
        $this->assertEquals('ecommerce', $posProduct2->inventory_source);
        $this->assertNull($posProduct2->pos_item_id);
        $this->assertEquals(3000, $posProduct2->price);
        $this->assertEquals(2200, $posProduct2->cost_price);
        $this->assertEquals(25, $posProduct2->stock);

        // Check ecomProduct (unchanged)
        $ecomProduct->refresh();
        $this->assertEquals('ecommerce', $ecomProduct->inventory_source);
        $this->assertNull($ecomProduct->pos_item_id);

        // Check SyncedPosItems were unmapped
        $this->assertEquals('unmapped', SyncedPosItem::where('pos_item_id', 2001)->value('mapping_status'));
        $this->assertEquals('unmapped', SyncedPosItem::where('pos_item_id', 2002)->value('mapping_status'));
    }

    public function test_bulk_move_fails_gracefully_if_no_pos_products_selected(): void
    {
        $ecomProduct = Product::create([
            'name' => 'Ecom Product',
            'slug' => 'ecom-prod',
            'url_key' => 'ecom-prod',
            'price' => 500,
            'stock' => 10,
            'inventory_source' => 'ecommerce',
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.products.bulk-action'), [
                'action' => 'move_to_ecommerce',
                'ids' => [$ecomProduct->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'None of the selected products are POS-linked items.');
    }
}
