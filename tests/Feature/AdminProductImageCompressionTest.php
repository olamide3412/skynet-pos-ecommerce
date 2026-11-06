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

class AdminProductImageCompressionTest extends TestCase
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
            'name' => 'Audio',
            'slug' => 'audio',
        ]);
    }

    public function test_it_compresses_thumbnail_and_gallery_images_to_webp_by_default(): void
    {
        $thumbnail = UploadedFile::fake()->image('headphone.jpg', 1600, 1200);
        $gallery1 = UploadedFile::fake()->image('gallery1.png', 1800, 1400);
        $gallery2 = UploadedFile::fake()->image('gallery2.jpg', 1500, 1000);

        $payload = [
            'name'                 => 'Noise Cancelling Headphones X1',
            'inventory_source'     => 'ecommerce',
            'url_key'              => 'noise-cancelling-headphones-x1',
            'category_id'          => $this->category->id,
            'price'                => 150000,
            'stock'                => 20,
            'status'               => true,
            'visible_individually' => true,
            'compress_images'      => '1',
            'image'                => $thumbnail,
            'images'               => [$gallery1, $gallery2],
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.products.store'), $payload);

        $response->assertRedirect();

        $product = Product::where('url_key', 'noise-cancelling-headphones-x1')->firstOrFail();

        $this->assertNotNull($product->image);
        $this->assertStringStartsWith('products/noise-cancelling-headphones-x1-', $product->image);
        $this->assertStringEndsWith('.webp', $product->image);
        Storage::disk('public')->assertExists($product->image);

        $this->assertIsArray($product->images);
        $this->assertCount(2, $product->images);
        $this->assertStringStartsWith('products/noise-cancelling-headphones-x1-gallery-1-', $product->images[0]);
        $this->assertStringStartsWith('products/noise-cancelling-headphones-x1-gallery-2-', $product->images[1]);
        foreach ($product->images as $galleryPath) {
            $this->assertStringEndsWith('.webp', $galleryPath);
            Storage::disk('public')->assertExists($galleryPath);
        }
    }

    public function test_it_respects_compression_toggle_when_disabled(): void
    {
        $thumbnail = UploadedFile::fake()->image('headphone_raw.png', 800, 600);

        $payload = [
            'name'                 => 'Raw Quality Headphones',
            'inventory_source'     => 'ecommerce',
            'url_key'              => 'raw-quality-headphones',
            'category_id'          => $this->category->id,
            'price'                => 120000,
            'stock'                => 10,
            'status'               => true,
            'visible_individually' => true,
            'compress_images'      => '0',
            'image'                => $thumbnail,
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.products.store'), $payload);

        $response->assertRedirect();

        $product = Product::where('url_key', 'raw-quality-headphones')->firstOrFail();

        $this->assertNotNull($product->image);
        $this->assertStringStartsWith('products/raw-quality-headphones-', $product->image);
        // When compression is off, it preserves the original extension (.png)
        $this->assertStringEndsWith('.png', $product->image);
        Storage::disk('public')->assertExists($product->image);
    }
}

