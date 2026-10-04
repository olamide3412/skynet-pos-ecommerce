<?php

namespace Tests\Feature;

use App\Enums\RoleEnums;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBulkActionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $categoryA;
    protected Category $categoryB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => RoleEnums::Administrator->value,
        ]);

        $this->categoryA = Category::create([
            'name' => 'Category A',
            'slug' => 'category-a',
        ]);

        $this->categoryB = Category::create([
            'name' => 'Category B',
            'slug' => 'category-b',
        ]);
    }

    protected function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name'                 => 'Test Product ' . uniqid(),
            'slug'                 => 'test-product-' . uniqid(),
            'url_key'              => 'test-product-' . uniqid(),
            'category_id'          => $this->categoryA->id,
            'price'                => 1000,
            'stock'                => 20,
            'status'               => true,
            'is_featured'          => false,
            'is_new'               => false,
            'visible_individually' => true,
            'inventory_source'     => 'ecommerce',
        ], $overrides));
    }

    public function test_bulk_update_category(): void
    {
        $p1 = $this->createProduct();
        $p2 = $this->createProduct();

        $response = $this->actingAs($this->admin)->post(route('admin.products.bulk-action'), [
            'ids'         => [$p1->id, $p2->id],
            'action'      => 'update_category',
            'category_id' => $this->categoryB->id,
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals($this->categoryB->id, $p1->fresh()->category_id);
        $this->assertEquals($this->categoryB->id, $p2->fresh()->category_id);
    }

    public function test_bulk_update_status(): void
    {
        $p1 = $this->createProduct(['status' => true]);
        $p2 = $this->createProduct(['status' => true]);

        // Make inactive
        $response = $this->actingAs($this->admin)->post(route('admin.products.bulk-action'), [
            'ids'    => [$p1->id, $p2->id],
            'action' => 'update_status',
            'status' => false,
        ]);

        $response->assertSessionHas('success');
        $this->assertFalse((bool) $p1->fresh()->status);
        $this->assertFalse((bool) $p2->fresh()->status);

        // Make active again
        $this->actingAs($this->admin)->post(route('admin.products.bulk-action'), [
            'ids'    => [$p1->id, $p2->id],
            'action' => 'update_status',
            'status' => true,
        ]);

        $this->assertTrue((bool) $p1->fresh()->status);
        $this->assertTrue((bool) $p2->fresh()->status);
    }

    public function test_bulk_update_featured_and_new(): void
    {
        $p1 = $this->createProduct(['is_featured' => false, 'is_new' => false]);

        $this->actingAs($this->admin)->post(route('admin.products.bulk-action'), [
            'ids'         => [$p1->id],
            'action'      => 'update_featured',
            'is_featured' => true,
        ]);
        $this->assertTrue((bool) $p1->fresh()->is_featured);

        $this->actingAs($this->admin)->post(route('admin.products.bulk-action'), [
            'ids'    => [$p1->id],
            'action' => 'update_new',
            'is_new' => true,
        ]);
        $this->assertTrue((bool) $p1->fresh()->is_new);
    }

    public function test_bulk_adjust_prices(): void
    {
        $p1 = $this->createProduct(['price' => 1000]);
        $p2 = $this->createProduct(['price' => 2000]);

        // Increase by 10%
        $this->actingAs($this->admin)->post(route('admin.products.bulk-action'), [
            'ids'             => [$p1->id, $p2->id],
            'action'          => 'update_prices',
            'price_operation' => 'increase',
            'price_type'      => 'percentage',
            'price_amount'    => 10,
        ]);

        $this->assertEquals(1100, (float) $p1->fresh()->price);
        $this->assertEquals(2200, (float) $p2->fresh()->price);
    }

    public function test_bulk_delete(): void
    {
        $p1 = $this->createProduct();
        $p2 = $this->createProduct();

        $response = $this->actingAs($this->admin)->post(route('admin.products.bulk-action'), [
            'ids'    => [$p1->id, $p2->id],
            'action' => 'delete',
        ]);

        $response->assertSessionHas('success');
        $this->assertNull(Product::find($p1->id));
        $this->assertNull(Product::find($p2->id));
    }
}
