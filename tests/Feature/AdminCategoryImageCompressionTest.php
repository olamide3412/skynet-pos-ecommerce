<?php

namespace Tests\Feature;

use App\Enums\RoleEnums;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCategoryImageCompressionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::factory()->create([
            'role' => RoleEnums::Administrator->value,
        ]);
    }

    public function test_it_compresses_category_thumbnail_to_webp_by_default_on_create(): void
    {
        $image = UploadedFile::fake()->image('category_banner.jpg', 1400, 1000);

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.categories.store'), [
                'name'            => 'Beverages & Soft Drinks',
                'slug'            => 'beverages-soft-drinks',
                'description'     => 'All refreshing drinks and sodas',
                'compress_images' => '1',
                'image'           => $image,
            ]);

        $response->assertRedirect();

        $category = Category::where('slug', 'beverages-soft-drinks')->firstOrFail();

        $this->assertNotNull($category->image);
        $this->assertStringStartsWith('categories/beverages-soft-drinks-', $category->image);
        $this->assertStringEndsWith('.webp', $category->image);
        Storage::disk('public')->assertExists($category->image);
    }

    public function test_it_respects_compression_toggle_when_disabled_on_create(): void
    {
        $image = UploadedFile::fake()->image('raw_category.png', 600, 400);

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.categories.store'), [
                'name'            => 'Raw Uncompressed Category',
                'slug'            => 'raw-uncompressed-category',
                'compress_images' => '0',
                'image'           => $image,
            ]);

        $response->assertRedirect();

        $category = Category::where('slug', 'raw-uncompressed-category')->firstOrFail();

        $this->assertNotNull($category->image);
        $this->assertStringStartsWith('categories/raw-uncompressed-category-', $category->image);
        $this->assertStringEndsWith('.png', $category->image);
        Storage::disk('public')->assertExists($category->image);
    }

    public function test_it_compresses_category_thumbnail_on_update_and_removes_old_image(): void
    {
        $oldImage = UploadedFile::fake()->image('initial.jpg', 800, 600);

        $category = Category::create([
            'name'  => 'Old Category Name',
            'slug'  => 'old-category-name',
            'image' => $oldImage->store('categories', 'public'),
        ]);

        Storage::disk('public')->assertExists($category->image);
        $oldImagePath = $category->image;

        $newImage = UploadedFile::fake()->image('updated.jpg', 1200, 900);

        $response = $this->actingAs($this->admin, 'web')
            ->put(route('admin.categories.update', $category->id), [
                'name'            => 'Updated Category Name',
                'slug'            => 'updated-category-name',
                'compress_images' => '1',
                'image'           => $newImage,
            ]);

        $response->assertRedirect();

        $category->refresh();

        $this->assertNotEquals($oldImagePath, $category->image);
        $this->assertStringStartsWith('categories/updated-category-name-', $category->image);
        $this->assertStringEndsWith('.webp', $category->image);
        Storage::disk('public')->assertExists($category->image);
        Storage::disk('public')->assertMissing($oldImagePath);
    }

    public function test_it_rejects_category_image_exceeding_1mb(): void
    {
        // 1.5 MB = 1536 KB (exceeds 1024 KB limit)
        $largeImage = UploadedFile::fake()->create('huge_image.jpg', 1536, 'image/jpeg');

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.categories.store'), [
                'name'  => 'Heavy Category',
                'slug'  => 'heavy-category',
                'image' => $largeImage,
            ]);

        $response->assertSessionHasErrors(['image']);
        $this->assertEquals(0, Category::where('slug', 'heavy-category')->count());
    }

    public function test_it_accepts_category_image_under_1mb(): void
    {
        // 800 KB (under 1024 KB limit)
        $validImage = UploadedFile::fake()->create('valid_image.jpg', 800, 'image/jpeg');

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.categories.store'), [
                'name'  => 'Valid Size Category',
                'slug'  => 'valid-size-category',
                'image' => $validImage,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, Category::where('slug', 'valid-size-category')->count());
    }
}

