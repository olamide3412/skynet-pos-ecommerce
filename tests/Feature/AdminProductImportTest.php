<?php

namespace Tests\Feature;

use App\Enums\RoleEnums;
use App\Models\Category;
use App\Models\Product;
use App\Models\SyncedPosItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminProductImportTest extends TestCase
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
            'name' => 'Groceries',
            'slug' => 'groceries',
        ]);
    }

    public function test_guest_cannot_access_import(): void
    {
        $file = UploadedFile::fake()->createWithContent('products.csv', "Name,Price\nTest,100");
        $response = $this->post(route('admin.products.import'), [
            'file' => $file,
        ]);
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_import_ecommerce_products(): void
    {
        $csvContent = "Name,Price,Stock,Category,Inventory Source\n"
                    . "Golden Morn 500g,1800,25,Groceries,ecommerce\n"
                    . "Milo Tin 400g,3200,10,Groceries,ecommerce\n";

        $file = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.products.import'), [
                'file' => $file,
                'import_mode' => 'ecommerce',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        expect(Product::where('name', 'Golden Morn 500g')->exists())->toBeTrue();
        expect(Product::where('name', 'Milo Tin 400g')->exists())->toBeTrue();

        $product = Product::where('name', 'Golden Morn 500g')->first();
        expect($product->inventory_source)->toBe('ecommerce');
        expect((float)$product->price)->toBe(1800.0);
        expect($product->stock)->toBe(25);
    }

    public function test_admin_imports_pos_item_when_available_and_skips_when_not_available(): void
    {
        // 1. Create one synced POS item in DB
        SyncedPosItem::create([
            'pos_item_id'            => 101,
            'pos_item_name'          => 'Peak Milk 400g',
            'standard_price'         => 2500,
            'effective_availability' => 50,
            'is_active'              => true,
            'mapping_status'         => 'unmapped',
        ]);

        // CSV has two POS items: #101 (exists in branch) and #999 (does not exist in branch)
        $csvContent = "Name,Price,Stock,Category,Inventory Source,POS Item ID\n"
                    . "Peak Milk 400g,2500,50,Groceries,pos,101\n"
                    . "Ghost POS Product,5000,10,Groceries,pos,999\n";

        $file = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.products.import'), [
                'file' => $file,
                'import_mode' => 'both',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('warning');

        // Check flash warning message mentions the skipped item
        $warning = session('warning');
        expect($warning)->toContain('1 POS item(s) skipped');
        expect($warning)->toContain('Ghost POS Product');

        // Available POS item was imported
        expect(Product::where('name', 'Peak Milk 400g')->exists())->toBeTrue();
        $importedPosProduct = Product::where('name', 'Peak Milk 400g')->first();
        expect($importedPosProduct->inventory_source)->toBe('pos');
        expect($importedPosProduct->pos_item_id)->toBe(101);

        // Unavailable POS item was skipped
        expect(Product::where('name', 'Ghost POS Product')->exists())->toBeFalse();

        // Synced POS item was marked as mapped
        expect(SyncedPosItem::where('pos_item_id', 101)->first()->mapping_status)->toBe('mapped');
    }

    public function test_pos_only_mode_skips_unavailable_pos_items(): void
    {
        // CSV with invalid POS item ID
        $csvContent = "Name,Price,Category,Inventory Source,POS Item ID\n"
                    . "Nonexistent Branch Item,1200,Groceries,pos,8888\n";

        $file = UploadedFile::fake()->createWithContent('pos_items.csv', $csvContent);

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.products.import'), [
                'file' => $file,
                'import_mode' => 'pos',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('warning');
        expect(session('warning'))->toContain('1 POS item(s) skipped');
        expect(Product::where('name', 'Nonexistent Branch Item')->exists())->toBeFalse();
    }

    public function test_admin_can_import_products_with_thumbnail_column(): void
    {
        $csvContent = "Name,Price,Stock,Category,Thumbnail,Inventory Source\n"
                    . "Item With Storage URL,3500,10,Groceries,http://localhost/storage/products/test-storage.webp,ecommerce\n"
                    . "Item With Relative Path,4500,20,Groceries,products/test-relative.webp,ecommerce\n";

        $file = UploadedFile::fake()->createWithContent('products_with_thumb.csv', $csvContent);

        $response = $this->actingAs($this->admin, 'web')
            ->post(route('admin.products.import'), [
                'file' => $file,
                'import_mode' => 'ecommerce',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $item1 = Product::where('name', 'Item With Storage URL')->first();
        expect($item1)->not->toBeNull();
        expect($item1->image)->toBe('products/test-storage.webp');

        $item2 = Product::where('name', 'Item With Relative Path')->first();
        expect($item2)->not->toBeNull();
        expect($item2->image)->toBe('products/test-relative.webp');
    }
}

