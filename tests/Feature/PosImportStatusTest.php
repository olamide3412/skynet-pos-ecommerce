<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PosConnection;
use App\Models\Product;
use App\Models\SyncedPosItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PosImportStatusTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected PosConnection $conn;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => \App\Enums\RoleEnums::Administrator->value,
        ]);

        $this->conn = new PosConnection();
        $this->conn->pos_base_url = 'https://pos.test';
        $this->conn->client_id = 'test_client';
        $this->conn->api_secret = 'test_secret';
        $this->conn->pos_branch_id = 1;
        $this->conn->pos_branch_name = 'Test Branch';
        $this->conn->is_connected = true;
        $this->conn->save();

        $this->category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        Http::fake([
            'https://pos.test/*' => Http::response(['success' => true, 'data' => []], 200),
        ]);
    }

    public function test_can_import_single_item_as_active_product(): void
    {
        $synced = SyncedPosItem::create([
            'pos_item_id'            => 101,
            'pos_item_name'          => 'Wireless Mouse',
            'sku'                    => 'MOU-101',
            'barcode'                => 'MOU-101',
            'effective_availability' => 25,
            'standard_price'         => 5000,
            'is_active'              => true,
            'mapping_status'         => 'unmapped',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.pos-integration.import-simple'), [
            'pos_item_id' => 101,
            'category_id' => $this->category->id,
            'is_active'   => true,
        ]);

        $response->assertSessionHas('success');
        $product = Product::where('pos_item_id', 101)->first();
        $this->assertNotNull($product);
        $this->assertTrue($product->status);
        $this->assertEquals('Wireless Mouse', $product->name);
        $this->assertEquals(5000, $product->price);
        $this->assertEquals(25, $product->stock);
    }

    public function test_can_import_single_item_as_draft_product(): void
    {
        $synced = SyncedPosItem::create([
            'pos_item_id'            => 102,
            'pos_item_name'          => 'Keyboard',
            'sku'                    => 'KEY-102',
            'barcode'                => 'KEY-102',
            'effective_availability' => 10,
            'standard_price'         => 8000,
            'is_active'              => true,
            'mapping_status'         => 'unmapped',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.pos-integration.import-simple'), [
            'pos_item_id' => 102,
            'category_id' => $this->category->id,
            'is_active'   => false,
        ]);

        $response->assertSessionHas('success');
        $product = Product::where('pos_item_id', 102)->first();
        $this->assertNotNull($product);
        $this->assertFalse($product->status);
    }

    public function test_bulk_import_sets_active_status_choice(): void
    {
        $item1 = SyncedPosItem::create([
            'pos_item_id'            => 201,
            'pos_item_name'          => 'Monitor',
            'sku'                    => 'MON-201',
            'effective_availability' => 5,
            'standard_price'         => 45000,
            'is_active'              => true,
            'mapping_status'         => 'unmapped',
        ]);

        $item2 = SyncedPosItem::create([
            'pos_item_id'            => 202,
            'pos_item_name'          => 'HDMI Cable',
            'sku'                    => 'CAB-202',
            'effective_availability' => 50,
            'standard_price'         => 2000,
            'is_active'              => true,
            'mapping_status'         => 'unmapped',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.pos-integration.bulk-import'), [
            'pos_item_ids' => [201, 202],
            'category_id'  => $this->category->id,
            'is_active'    => true,
        ]);

        $response->assertSessionHas('success');
        $p1 = Product::where('pos_item_id', 201)->first();
        $p2 = Product::where('pos_item_id', 202)->first();

        $this->assertNotNull($p1);
        $this->assertNotNull($p2);
        $this->assertTrue($p1->status);
        $this->assertTrue($p2->status);
    }

    public function test_import_all_eligible_sets_active_status_choice(): void
    {
        SyncedPosItem::create([
            'pos_item_id'            => 301,
            'pos_item_name'          => 'Webcam',
            'sku'                    => 'CAM-301',
            'effective_availability' => 8,
            'standard_price'         => 12000,
            'is_active'              => true,
            'mapping_status'         => 'unmapped',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.pos-integration.import-all'), [
            'category_id' => $this->category->id,
            'is_active'   => true,
        ]);

        $response->assertSessionHas('success');
        $product = Product::where('pos_item_id', 301)->first();
        $this->assertNotNull($product);
        $this->assertTrue($product->status);
    }
}

