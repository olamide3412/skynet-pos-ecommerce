<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

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
    }

    public function test_admin_can_create_edit_and_delete_brand_with_logo(): void
    {
        Storage::fake('public');

        // 1. Create Brand with Logo
        $logo = UploadedFile::fake()->image('samsung-logo.png', 200, 200);

        $response = $this->actingAs($this->admin, 'web')->post(route('admin.brands.store'), [
            'name' => 'Samsung Electronics',
            'description' => 'Global electronics leader',
            'status' => true,
            'is_featured' => true,
            'sort_order' => 5,
            'meta_title' => 'Samsung Official Store',
            'meta_description' => 'Buy authentic Samsung products',
            'logo' => $logo,
            'compress_logo' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $brand = Brand::where('name', 'Samsung Electronics')->first();
        $this->assertNotNull($brand);
        $this->assertEquals('samsung-electronics', $brand->slug);
        $this->assertTrue($brand->status);
        $this->assertTrue($brand->is_featured);
        $this->assertNotNull($brand->logo);
        Storage::disk('public')->assertExists($brand->logo);

        // 2. Update Brand
        $updateResponse = $this->actingAs($this->admin, 'web')->put(route('admin.brands.update', $brand), [
            'name' => 'Samsung',
            'slug' => 'samsung',
            'description' => 'Updated Samsung description',
            'status' => true,
            'is_featured' => false,
            'sort_order' => 1,
        ]);

        $updateResponse->assertRedirect();
        $brand->refresh();
        $this->assertEquals('Samsung', $brand->name);
        $this->assertEquals('samsung', $brand->slug);
        $this->assertFalse($brand->is_featured);

        // 3. Delete unused brand
        $deleteResponse = $this->actingAs($this->admin, 'web')->delete(route('admin.brands.destroy', $brand));
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }

    public function test_deleting_brand_with_assigned_products_warns_unless_detached(): void
    {
        $brand = Brand::create([
            'name' => 'Apple',
            'status' => true,
        ]);

        $category = Category::create([
            'name' => 'Phones',
            'slug' => 'phones',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'iPhone 16 Pro',
            'slug' => 'iphone-16-pro',
            'url_key' => 'iphone-16-pro',
            'price' => 1500000,
            'stock' => 5,
            'status' => true,
        ]);

        // Without detach=1 -> blocked with error flash
        $res1 = $this->actingAs($this->admin, 'web')->delete(route('admin.brands.destroy', $brand));
        $res1->assertRedirect();
        $res1->assertSessionHas('error');
        $this->assertDatabaseHas('brands', ['id' => $brand->id]);

        // With detach=1 -> detaches product and deletes brand
        $res2 = $this->actingAs($this->admin, 'web')->delete(route('admin.brands.destroy', ['brand' => $brand->id, 'detach' => 1]));
        $res2->assertRedirect();
        $res2->assertSessionHas('success');
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
        $this->assertNull($product->fresh()->brand_id);
    }

    public function test_ecommerce_only_product_can_be_created_with_brand_without_pos_connection(): void
    {
        $brand = Brand::create([
            'name' => 'Sony',
            'status' => true,
        ]);

        $category = Category::create([
            'name' => 'Audio',
            'slug' => 'audio',
        ]);

        $response = $this->actingAs($this->admin, 'web')->post(route('admin.products.store'), [
            'inventory_source' => 'ecommerce',
            'name' => 'Sony WH-1000XM5 Headphones',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'url_key' => 'sony-wh-1000xm5',
            'price' => 450000,
            'stock' => 12,
            'status' => true,
            'is_new' => true,
            'is_featured' => false,
            'visible_individually' => true,
            'show_stock_level' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', [
            'name' => 'Sony WH-1000XM5 Headphones',
            'brand_id' => $brand->id,
            'inventory_source' => 'ecommerce',
        ]);
    }

    public function test_storefront_brand_filter_and_dedicated_brand_page_and_json_ld(): void
    {
        $lg = Brand::create(['name' => 'LG', 'slug' => 'lg', 'status' => true]);
        $hisense = Brand::create(['name' => 'Hisense', 'slug' => 'hisense', 'status' => true]);
        $category = Category::create(['name' => 'TVs', 'slug' => 'tvs']);

        $lgProduct = Product::create([
            'category_id' => $category->id,
            'brand_id' => $lg->id,
            'name' => 'LG OLED C4 65 Inch',
            'slug' => 'lg-oled-c4-65',
            'url_key' => 'lg-oled-c4-65',
            'price' => 1800000,
            'stock' => 4,
            'status' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'brand_id' => $hisense->id,
            'name' => 'Hisense U7K 55 Inch',
            'slug' => 'hisense-u7k-55',
            'url_key' => 'hisense-u7k-55',
            'price' => 650000,
            'stock' => 8,
            'status' => true,
        ]);

        // 1. Storefront filter ?brand=lg
        $shopResponse = $this->get(route('shop.index', ['brand' => 'lg']));
        $shopResponse->assertStatus(200);
        $shopResponse->assertInertia(
            fn($page) => $page
                ->component('Frontend/Products/Index')
                ->has('products.data', 1)
                ->where('products.data.0.name', 'LG OLED C4 65 Inch')
                ->where('products.data.0.brand.name', 'LG')
        );

        // 2. Dedicated brand landing page /brand/lg
        $brandPageResponse = $this->get(route('shop.brand', 'lg'));
        $brandPageResponse->assertStatus(200);
        $brandPageResponse->assertInertia(
            fn($page) => $page
                ->component('Frontend/Products/Index')
                ->where('current_brand.slug', 'lg')
                ->has('products.data', 1)
        );

        // 3. Product detail page includes brand prop for JSON-LD and badge
        $detailResponse = $this->get(route('shop.show', $lgProduct->url_key));
        $detailResponse->assertStatus(200);
        $detailResponse->assertInertia(
            fn($page) => $page
                ->component('Frontend/Products/Show')
                ->where('product.brand.name', 'LG')
                ->where('product.brand.slug', 'lg')
        );
    }

    public function test_bulk_action_updates_brand_and_clears_brand(): void
    {
        $brand = Brand::create(['name' => 'Logitech', 'status' => true]);
        $category = Category::create(['name' => 'Accessories', 'slug' => 'accessories']);

        $p1 = Product::create([
            'category_id' => $category->id,
            'name' => 'MX Master 3S',
            'slug' => 'mx-master-3s',
            'url_key' => 'mx-master-3s',
            'price' => 120000,
            'stock' => 10,
            'status' => true,
        ]);

        $p2 = Product::create([
            'category_id' => $category->id,
            'name' => 'MX Keys S',
            'slug' => 'mx-keys-s',
            'url_key' => 'mx-keys-s',
            'price' => 150000,
            'stock' => 7,
            'status' => true,
        ]);

        // Bulk assign brand
        $this->actingAs($this->admin, 'web')->post(route('admin.products.bulk-action'), [
            'ids' => [$p1->id, $p2->id],
            'action' => 'update_brand',
            'brand_id' => $brand->id,
        ])->assertRedirect();

        $this->assertEquals($brand->id, $p1->fresh()->brand_id);
        $this->assertEquals($brand->id, $p2->fresh()->brand_id);

        // Bulk remove brand (null)
        $this->actingAs($this->admin, 'web')->post(route('admin.products.bulk-action'), [
            'ids' => [$p1->id, $p2->id],
            'action' => 'update_brand',
            'brand_id' => null,
        ])->assertRedirect();

        $this->assertNull($p1->fresh()->brand_id);
        $this->assertNull($p2->fresh()->brand_id);
    }
}