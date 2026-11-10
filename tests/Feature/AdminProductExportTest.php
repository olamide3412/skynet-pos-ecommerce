<?php

namespace Tests\Feature;

use App\Enums\RoleEnums;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductExportTest extends TestCase
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
            'name' => 'Beverages',
            'slug' => 'beverages',
        ]);
    }

    public function test_guest_cannot_access_export(): void
    {
        $response = $this->get(route('admin.products.export'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_export_both_ecommerce_and_pos_items(): void
    {
        Product::create([
            'name'             => 'Ecommerce Milk 500ml',
            'slug'             => 'ecommerce-milk-500ml',
            'url_key'          => 'ecommerce-milk-500ml',
            'product_number'   => 'MILK-001',
            'price'            => 1200,
            'category_id'      => $this->category->id,
            'status'           => true,
            'stock'            => 15,
            'inventory_source' => 'ecommerce',
        ]);

        Product::create([
            'name'             => 'POS Bread 800g',
            'slug'             => 'pos-bread-800g',
            'url_key'          => 'pos-bread-800g',
            'product_number'   => 'BREAD-001',
            'price'            => 1500,
            'category_id'      => $this->category->id,
            'status'           => true,
            'stock'            => 30,
            'inventory_source' => 'pos',
            'pos_item_id'      => 9991,
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->get(route('admin.products.export', ['source' => 'both']));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('products_export_all_', $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Ecommerce Milk 500ml', $content);
        $this->assertStringContainsString('POS Bread 800g', $content);
        $this->assertStringContainsString('Online Warehouse (Ecommerce Only)', $content);
        $this->assertStringContainsString('Physical POS Branch Item', $content);
    }

    public function test_admin_can_export_ecommerce_items_only(): void
    {
        Product::create([
            'name'             => 'Exclusive Online Shoes',
            'slug'             => 'exclusive-online-shoes',
            'url_key'          => 'exclusive-online-shoes',
            'product_number'   => 'SHOE-001',
            'price'            => 25000,
            'category_id'      => $this->category->id,
            'status'           => true,
            'stock'            => 8,
            'inventory_source' => 'ecommerce',
        ]);

        Product::create([
            'name'             => 'In-Store Physical Coffee',
            'slug'             => 'in-store-physical-coffee',
            'url_key'          => 'in-store-physical-coffee',
            'product_number'   => 'COF-001',
            'price'            => 3500,
            'category_id'      => $this->category->id,
            'status'           => true,
            'stock'            => 20,
            'inventory_source' => 'pos',
            'pos_item_id'      => 8882,
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->get(route('admin.products.export', ['source' => 'ecommerce']));

        $response->assertStatus(200);
        $this->assertStringContainsString('products_export_ecommerce_only_', $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Exclusive Online Shoes', $content);
        $this->assertStringNotContainsString('In-Store Physical Coffee', $content);
    }

    public function test_admin_can_export_pos_items_only(): void
    {
        Product::create([
            'name'             => 'Exclusive Online Jacket',
            'slug'             => 'exclusive-online-jacket',
            'url_key'          => 'exclusive-online-jacket',
            'product_number'   => 'JAC-001',
            'price'            => 45000,
            'category_id'      => $this->category->id,
            'status'           => true,
            'stock'            => 5,
            'inventory_source' => 'ecommerce',
        ]);

        Product::create([
            'name'             => 'POS Branch Soda',
            'slug'             => 'pos-branch-soda',
            'url_key'          => 'pos-branch-soda',
            'product_number'   => 'SODA-001',
            'price'            => 500,
            'category_id'      => $this->category->id,
            'status'           => true,
            'stock'            => 100,
            'inventory_source' => 'pos',
            'pos_item_id'      => 7773,
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->get(route('admin.products.export', ['source' => 'pos']));

        $response->assertStatus(200);
        $this->assertStringContainsString('products_export_pos_only_', $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('POS Branch Soda', $content);
        $this->assertStringNotContainsString('Exclusive Online Jacket', $content);
        $this->assertStringContainsString('7773', $content);
    }
}

