<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDiscountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->create([
            'role' => \App\Enums\RoleEnums::Administrator->value,
        ]);

        $this->category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);
    }

    protected function createProduct(array $attributes = []): Product
    {
        $name = $attributes['name'] ?? 'Sample Product ' . Str::random(5);
        $slug = $attributes['slug'] ?? Str::slug($name);

        return Product::create(array_merge([
            'name'        => $name,
            'slug'        => $slug,
            'url_key'     => $slug,
            'sku'         => 'SKU-' . strtoupper(Str::random(6)),
            'price'       => 10000,
            'stock'       => 10,
            'category_id' => $this->category->id,
            'status'      => true,
        ], $attributes));
    }

    public function test_admin_can_view_discounts_index()
    {
        // 1. Regular non-discounted product
        $this->createProduct([
            'name'  => 'Plain Product',
            'price' => 10000,
        ]);

        // 2. Product with standard markdown
        $this->createProduct([
            'name'           => 'Discounted Product',
            'price'          => 20000,
            'discount_price' => 15000,
        ]);

        // 3. Product with flash sale
        $this->createProduct([
            'name'               => 'Flash Sale Product',
            'price'              => 50000,
            'special_price'      => 35000,
            'special_price_from' => Carbon::now()->subHour(),
            'special_price_to'   => Carbon::now()->addDay(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.discounts.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Discounts/Index')
            ->has('products.data', 2) // Only the 2 discounted products show up
            ->where('stats.total_promotions', 2)
            ->where('stats.active_flash', 1)
            ->where('stats.markdowns', 1)
        );
    }

    public function test_admin_can_filter_active_flash_sales()
    {
        // Active flash sale
        $this->createProduct([
            'name'               => 'Active Flash',
            'sku'                => 'ACT-001',
            'price'              => 30000,
            'special_price'      => 20000,
            'special_price_from' => Carbon::now()->subDay(),
            'special_price_to'   => Carbon::now()->addDay(),
        ]);

        // Expired flash sale
        $this->createProduct([
            'name'               => 'Expired Flash',
            'sku'                => 'EXP-001',
            'price'              => 40000,
            'special_price'      => 25000,
            'special_price_from' => Carbon::now()->subDays(5),
            'special_price_to'   => Carbon::now()->subDay(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.discounts.index', ['tab' => 'active_flash']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Discounts/Index')
            ->has('products.data', 1)
            ->where('products.data.0.sku', 'ACT-001')
        );
    }

    public function test_product_search_autocomplete_for_discounts()
    {
        $this->createProduct([
            'name'  => 'Wireless Headphones Pro',
            'sku'   => 'WHP-889',
            'price' => 45000,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('admin.discounts.search-products', ['q' => 'Headphones']));

        $response->assertOk();
        $response->assertJsonCount(1, 'products');
        $response->assertJsonFragment(['sku' => 'WHP-889']);
    }

    public function test_admin_can_apply_flash_sale_to_product()
    {
        $product = $this->createProduct([
            'name'  => 'Smart Watch Series 5',
            'price' => 80000,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.discounts.apply'), [
            'target_type'        => 'single',
            'product_id'         => $product->id,
            'apply_flash_sale'   => true,
            'flash_mode'         => 'percent',
            'flash_value'        => 20, // 20% off 80000 = 64000
            'special_price_from' => Carbon::now()->toDateTimeString(),
            'special_price_to'   => Carbon::now()->addDays(3)->toDateTimeString(),
        ]);

        $response->assertRedirect();
        $product->refresh();

        $this->assertEquals(64000.0, (float) $product->special_price);
        $this->assertTrue($product->is_on_flash_sale);
        $this->assertEquals(64000.0, (float) $product->effective_price);
    }

    public function test_admin_can_apply_discount_to_category()
    {
        $prod1 = $this->createProduct([
            'name'  => 'Laptop Alpha',
            'price' => 100000,
        ]);

        $prod2 = $this->createProduct([
            'name'  => 'Laptop Beta',
            'price' => 200000,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.discounts.apply'), [
            'target_type'    => 'category',
            'category_id'    => $this->category->id,
            'apply_markdown' => true,
            'markdown_mode'  => 'percent',
            'markdown_value' => 10, // 10% off
        ]);

        $response->assertRedirect();
        $prod1->refresh();
        $prod2->refresh();

        $this->assertEquals(90000.0, (float) $prod1->discount_price);
        $this->assertEquals(180000.0, (float) $prod2->discount_price);
    }

    public function test_admin_can_quick_edit_and_clear_promotion()
    {
        $product = $this->createProduct([
            'name'           => 'Tablet 10',
            'price'          => 50000,
            'discount_price' => 45000,
            'special_price'  => 40000,
        ]);

        // Quick edit
        $updateResp = $this->actingAs($this->admin)->put(route('admin.discounts.update', $product->id), [
            'discount_price' => 42000,
            'special_price'  => 38000,
        ]);
        $updateResp->assertRedirect();
        $product->refresh();
        $this->assertEquals(42000.0, (float) $product->discount_price);
        $this->assertEquals(38000.0, (float) $product->special_price);

        // Delete flash sale only
        $deleteFlash = $this->actingAs($this->admin)->delete(route('admin.discounts.destroy', $product->id) . '?scope=flash');
        $deleteFlash->assertRedirect();
        $product->refresh();
        $this->assertNull($product->special_price);
        $this->assertEquals(42000.0, (float) $product->discount_price);

        // Delete all promotions
        $deleteAll = $this->actingAs($this->admin)->delete(route('admin.discounts.destroy', $product->id) . '?scope=all');
        $deleteAll->assertRedirect();
        $product->refresh();
        $this->assertNull($product->discount_price);
        $this->assertNull($product->special_price);
    }

    public function test_bulk_clear_promotions()
    {
        $p1 = $this->createProduct([
            'name'           => 'Item A',
            'price'          => 10000,
            'discount_price' => 8000,
        ]);

        $p2 = $this->createProduct([
            'name'          => 'Item B',
            'price'         => 20000,
            'special_price' => 15000,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.discounts.bulk-action'), [
            'product_ids' => [$p1->id, $p2->id],
            'action'      => 'clear_all',
        ]);

        $response->assertRedirect();
        $p1->refresh();
        $p2->refresh();

        $this->assertNull($p1->discount_price);
        $this->assertNull($p2->special_price);
    }
}
