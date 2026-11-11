<?php

namespace Tests\Feature;

use App\Enums\RoleEnums;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductImageRemovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->create([
            'role' => RoleEnums::Administrator->value,
        ]);

        $this->category = Category::create([
            'name' => 'Groceries',
            'slug' => 'groceries',
        ]);
    }

    public function test_admin_can_remove_thumbnail_image_using_x_button_flag(): void
    {
        // 1. Create a product with a stored thumbnail image
        Storage::disk('public')->put('products/peak-milk.webp', 'fake image content');

        $product = Product::create([
            'name'                 => 'Peak Evaporated Milk',
            'slug'                 => 'peak-evaporated-milk-1',
            'url_key'              => 'peak-evaporated-milk',
            'category_id'          => $this->category->id,
            'price'                => 850,
            'stock'                => 50,
            'status'               => true,
            'visible_individually' => true,
            'inventory_source'     => 'ecommerce',
            'image'                => 'products/peak-milk.webp',
        ]);

        $this->assertTrue(Storage::disk('public')->exists('products/peak-milk.webp'));

        // 2. Submit update with remove_thumbnail = 1
        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.products.update', $product->id), [
                '_method'              => 'PUT',
                'name'                 => 'Peak Evaporated Milk',
                'url_key'              => 'peak-evaporated-milk',
                'category_id'          => $this->category->id,
                'price'                => 850,
                'stock'                => 50,
                'status'               => '1',
                'visible_individually' => '1',
                'inventory_source'     => 'ecommerce',
                'remove_thumbnail'     => '1',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $product->refresh();

        // 3. Verify thumbnail is null and old file was deleted from disk
        $this->assertNull($product->image);
        $this->assertFalse(Storage::disk('public')->exists('products/peak-milk.webp'));
    }

    public function test_admin_can_remove_specific_gallery_images(): void
    {
        // 1. Create product with 3 gallery images
        Storage::disk('public')->put('products/gallery-1.webp', 'content 1');
        Storage::disk('public')->put('products/gallery-2.webp', 'content 2');
        Storage::disk('public')->put('products/gallery-3.webp', 'content 3');

        $product = Product::create([
            'name'                 => 'Peak Milk Bundle',
            'slug'                 => 'peak-milk-bundle-1',
            'url_key'              => 'peak-milk-bundle',
            'category_id'          => $this->category->id,
            'price'                => 2500,
            'stock'                => 15,
            'status'               => true,
            'visible_individually' => true,
            'inventory_source'     => 'ecommerce',
            'images'               => [
                'products/gallery-1.webp',
                'products/gallery-2.webp',
                'products/gallery-3.webp',
            ],
        ]);

        // 2. Retain only gallery 1 and 3 (removing gallery 2)
        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.products.update', $product->id), [
                '_method'              => 'PUT',
                'name'                 => 'Peak Milk Bundle',
                'url_key'              => 'peak-milk-bundle',
                'category_id'          => $this->category->id,
                'price'                => 2500,
                'stock'                => 15,
                'status'               => '1',
                'visible_individually' => '1',
                'inventory_source'     => 'ecommerce',
                'existing_images'      => json_encode([
                    'products/gallery-1.webp',
                    'products/gallery-3.webp',
                ]),
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $product->refresh();

        // 3. Verify gallery 2 was deleted from storage and only 1 and 3 remain
        $this->assertSame([
            'products/gallery-1.webp',
            'products/gallery-3.webp',
        ], $product->images);

        $this->assertTrue(Storage::disk('public')->exists('products/gallery-1.webp'));
        $this->assertFalse(Storage::disk('public')->exists('products/gallery-2.webp'));
        $this->assertTrue(Storage::disk('public')->exists('products/gallery-3.webp'));
    }

    public function test_admin_can_clear_all_gallery_images(): void
    {
        Storage::disk('public')->put('products/gallery-1.webp', 'content 1');

        $product = Product::create([
            'name'                 => 'Single Gallery Product',
            'slug'                 => 'single-gallery-product-1',
            'url_key'              => 'single-gallery-product',
            'category_id'          => $this->category->id,
            'price'                => 1000,
            'stock'                => 10,
            'status'               => true,
            'visible_individually' => true,
            'inventory_source'     => 'ecommerce',
            'images'               => ['products/gallery-1.webp'],
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.products.update', $product->id), [
                '_method'              => 'PUT',
                'name'                 => 'Single Gallery Product',
                'url_key'              => 'single-gallery-product',
                'category_id'          => $this->category->id,
                'price'                => 1000,
                'stock'                => 10,
                'status'               => '1',
                'visible_individually' => '1',
                'inventory_source'     => 'ecommerce',
                'existing_images'      => json_encode([]),
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $product->refresh();

        $this->assertSame([], $product->images);
        $this->assertFalse(Storage::disk('public')->exists('products/gallery-1.webp'));
    }
}

