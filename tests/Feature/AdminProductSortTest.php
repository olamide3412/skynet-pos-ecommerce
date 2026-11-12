<?php

namespace Tests\Feature;

use App\Enums\RoleEnums;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductSortTest extends TestCase
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

    public function test_can_sort_products_alphabetically(): void
    {
        Product::create([
            'name' => 'Zebra Socks',
            'slug' => 'zebra-socks',
            'url_key' => 'zebra-socks',
            'price' => 10,
            'stock' => 50,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'Apple Fruit',
            'slug' => 'apple-fruit',
            'url_key' => 'apple-fruit',
            'price' => 20,
            'stock' => 10,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        // A -> Z
        $responseAsc = $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['sort' => 'name_asc']));

        $responseAsc->assertOk();
        $responseAsc->assertInertia(fn ($page) => $page
            ->component('Admin/Products/Index')
            ->has('products.data', 2)
            ->where('products.data.0.name', 'Apple Fruit')
            ->where('products.data.1.name', 'Zebra Socks')
        );

        // Z -> A
        $responseDesc = $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['sort' => 'name_desc']));

        $responseDesc->assertOk();
        $responseDesc->assertInertia(fn ($page) => $page
            ->component('Admin/Products/Index')
            ->has('products.data', 2)
            ->where('products.data.0.name', 'Zebra Socks')
            ->where('products.data.1.name', 'Apple Fruit')
        );
    }

    public function test_can_sort_products_by_stock_and_price(): void
    {
        Product::create([
            'name' => 'Low Stock Cheap Item',
            'slug' => 'low-stock-cheap',
            'url_key' => 'low-stock-cheap',
            'price' => 5,
            'stock' => 3,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'High Stock Expensive Item',
            'slug' => 'high-stock-expensive',
            'url_key' => 'high-stock-expensive',
            'price' => 500,
            'stock' => 999,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        // Stock: High to Low
        $responseStockDesc = $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['sort' => 'qty_desc']));

        $responseStockDesc->assertOk();
        $responseStockDesc->assertInertia(fn ($page) => $page
            ->where('products.data.0.name', 'High Stock Expensive Item')
            ->where('products.data.1.name', 'Low Stock Cheap Item')
        );

        // Stock: Low to High
        $responseStockAsc = $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['sort' => 'qty_asc']));

        $responseStockAsc->assertOk();
        $responseStockAsc->assertInertia(fn ($page) => $page
            ->where('products.data.0.name', 'Low Stock Cheap Item')
            ->where('products.data.1.name', 'High Stock Expensive Item')
        );

        // Price: Low to High
        $responsePriceAsc = $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['sort' => 'price_asc']));

        $responsePriceAsc->assertOk();
        $responsePriceAsc->assertInertia(fn ($page) => $page
            ->where('products.data.0.name', 'Low Stock Cheap Item')
            ->where('products.data.1.name', 'High Stock Expensive Item')
        );

        // Price: High to Low
        $responsePriceDesc = $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['sort' => 'price_desc']));

        $responsePriceDesc->assertOk();
        $responsePriceDesc->assertInertia(fn ($page) => $page
            ->where('products.data.0.name', 'High Stock Expensive Item')
            ->where('products.data.1.name', 'Low Stock Cheap Item')
        );
    }

    public function test_export_preserves_sort(): void
    {
        Product::create([
            'name' => 'Zebra',
            'slug' => 'zebra',
            'url_key' => 'zebra',
            'price' => 10,
            'stock' => 5,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'Ant',
            'slug' => 'ant',
            'url_key' => 'ant',
            'price' => 20,
            'stock' => 15,
            'category_id' => $this->category->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.products.export', ['sort' => 'name_asc']));

        $response->assertOk();
        $content = $response->streamedContent();

        $antPos = strpos($content, 'Ant');
        $zebraPos = strpos($content, 'Zebra');

        $this->assertNotFalse($antPos);
        $this->assertNotFalse($zebraPos);
        $this->assertTrue($antPos < $zebraPos, 'Ant should appear before Zebra when sorted by name_asc');
    }

    public function test_can_filter_products_by_inventory_source(): void
    {
        Product::create([
            'name'             => 'Ecommerce Product A',
            'slug'             => 'ecommerce-product-a',
            'url_key'          => 'ecommerce-product-a',
            'price'            => 10,
            'stock'            => 5,
            'category_id'      => $this->category->id,
            'status'           => 'active',
            'inventory_source' => 'ecommerce',
        ]);

        Product::create([
            'name'             => 'POS Product B',
            'slug'             => 'pos-product-b',
            'url_key'          => 'pos-product-b',
            'price'            => 20,
            'stock'            => 15,
            'category_id'      => $this->category->id,
            'status'           => 'active',
            'inventory_source' => 'pos',
            'pos_item_id'      => 456,
        ]);

        // Filter by ecommerce
        $responseEcom = $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['inventory_source' => 'ecommerce']));
        $responseEcom->assertOk();
        $productsEcom = $responseEcom->original->getData()['page']['props']['products']['data'];
        $this->assertCount(1, $productsEcom);
        $this->assertEquals('Ecommerce Product A', $productsEcom[0]['name']);

        // Filter by pos
        $responsePos = $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['inventory_source' => 'pos']));
        $responsePos->assertOk();
        $productsPos = $responsePos->original->getData()['page']['props']['products']['data'];
        $this->assertCount(1, $productsPos);
        $this->assertEquals('POS Product B', $productsPos[0]['name']);

        // Both / all
        $responseAll = $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['inventory_source' => '']));
        $responseAll->assertOk();
        $productsAll = $responseAll->original->getData()['page']['props']['products']['data'];
        $this->assertCount(2, $productsAll);
    }
}
