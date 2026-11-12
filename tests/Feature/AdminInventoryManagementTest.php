<?php

namespace Tests\Feature;

use App\Enums\RoleEnums;
use App\Models\Category;
use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->admin = User::factory()->create([
            'role'   => RoleEnums::Administrator->value,
            'status' => 'enable',
        ]);

        $this->category = Category::create([
            'name' => 'General',
            'slug' => 'general',
        ]);
    }

    public function test_guest_cannot_access_inventory_routes(): void
    {
        $this->get(route('admin.inventory.adjustments.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.inventory.logs.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.purchases.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.inventory.adjustments.store'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_access_inventory_adjustments_page_and_preselect_product(): void
    {
        $product = Product::create([
            'name'             => 'Adjustable Ecommerce Item',
            'slug'             => 'adjustable-ecommerce-item',
            'url_key'          => 'adjustable-ecommerce-item',
            'sku'              => 'ADJ-PRE-01',
            'product_number'   => 'ADJ-PRE-01',
            'price'            => 4500,
            'category_id'      => $this->category->id,
            'status'           => true,
            'stock'            => 12,
            'inventory_source' => 'ecommerce',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.adjustments.index', [
            'product_id' => $product->id,
        ]));

        $response->assertOk();
    }

    public function test_admin_can_adjust_stock_add_for_ecommerce_product(): void
    {
        $product = Product::create([
            'name'             => 'Test Wireless Mouse',
            'slug'             => 'test-wireless-mouse',
            'url_key'          => 'test-wireless-mouse',
            'sku'              => 'MOUSE-001',
            'product_number'   => 'MOUSE-001',
            'price'            => 5000,
            'category_id'      => $this->category->id,
            'status'           => true,
            'stock'            => 10,
            'inventory_source' => 'ecommerce',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.inventory.adjustments.store'), [
            'product_id' => $product->id,
            'mode'       => 'add',
            'quantity'   => 5,
            'reason'     => 'restock',
            'notes'      => 'Received supplementary batch',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(15, $product->fresh()->stock);

        $this->assertDatabaseHas('inventory_logs', [
            'product_id'      => $product->id,
            'action_type'     => 'adjustment',
            'quantity_change' => 5,
            'previous_stock'  => 10,
            'new_stock'       => 15,
            'reason'          => 'restock',
            'user_id'         => $this->admin->id,
            'reference_type'  => 'Manual Adjustment',
        ]);
    }

    public function test_admin_can_adjust_stock_subtract_and_set(): void
    {
        $product = Product::create([
            'name'             => 'Test Mechanical Keyboard',
            'slug'             => 'test-mechanical-keyboard',
            'url_key'          => 'test-mechanical-keyboard',
            'sku'              => 'KB-001',
            'product_number'   => 'KB-001',
            'price'            => 12000,
            'category_id'      => $this->category->id,
            'status'           => true,
            'stock'            => 20,
            'inventory_source' => 'ecommerce',
        ]);

        // Subtract 4
        $this->actingAs($this->admin)->post(route('admin.inventory.adjustments.store'), [
            'product_id' => $product->id,
            'mode'       => 'subtract',
            'quantity'   => 4,
            'reason'     => 'damaged',
            'notes'      => 'Broken during packaging',
        ])->assertSessionHas('success');

        $this->assertEquals(16, $product->fresh()->stock);

        $this->assertDatabaseHas('inventory_logs', [
            'product_id'      => $product->id,
            'action_type'     => 'adjustment',
            'quantity_change' => -4,
            'previous_stock'  => 20,
            'new_stock'       => 16,
            'reason'          => 'damaged',
        ]);

        // Set to 30
        $this->actingAs($this->admin)->post(route('admin.inventory.adjustments.store'), [
            'product_id' => $product->id,
            'mode'       => 'set',
            'quantity'   => 30,
            'reason'     => 'count_correction',
            'notes'      => 'Annual audit recount',
        ])->assertSessionHas('success');

        $this->assertEquals(30, $product->fresh()->stock);

        $this->assertDatabaseHas('inventory_logs', [
            'product_id'      => $product->id,
            'action_type'     => 'adjustment',
            'quantity_change' => 14,
            'previous_stock'  => 16,
            'new_stock'       => 30,
            'reason'          => 'count_correction',
        ]);
    }

    public function test_adjusting_stock_cannot_reduce_below_zero(): void
    {
        $product = Product::create([
            'name'             => 'Limited Mug',
            'slug'             => 'limited-mug',
            'url_key'          => 'limited-mug',
            'sku'              => 'MUG-001',
            'product_number'   => 'MUG-001',
            'price'            => 1500,
            'category_id'      => $this->category->id,
            'status'           => true,
            'stock'            => 3,
            'inventory_source' => 'ecommerce',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.inventory.adjustments.store'), [
            'product_id' => $product->id,
            'mode'       => 'subtract',
            'quantity'   => 10,
            'reason'     => 'damaged',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(3, $product->fresh()->stock);
        $this->assertEquals(0, InventoryLog::count());
    }

    public function test_physical_pos_product_cannot_be_manually_adjusted(): void
    {
        $posProduct = Product::create([
            'name'             => 'POS Soda Can',
            'slug'             => 'pos-soda-can',
            'url_key'          => 'pos-soda-can',
            'sku'              => 'SODA-POS',
            'product_number'   => 'SODA-POS',
            'price'            => 300,
            'category_id'      => $this->category->id,
            'status'           => true,
            'stock'            => 50,
            'inventory_source' => 'pos',
            'pos_item_id'      => 999,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.inventory.adjustments.store'), [
            'product_id' => $posProduct->id,
            'mode'       => 'add',
            'quantity'   => 10,
            'reason'     => 'restock',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(50, $posProduct->fresh()->stock);
        $this->assertEquals(0, InventoryLog::count());
    }

    public function test_order_deduction_and_cancellation_writes_to_inventory_ledger(): void
    {
        $product = Product::create([
            'name'             => 'Online Book',
            'slug'             => 'online-book',
            'url_key'          => 'online-book',
            'sku'              => 'BOOK-001',
            'product_number'   => 'BOOK-001',
            'price'            => 2500,
            'category_id'      => $this->category->id,
            'status'           => true,
            'stock'            => 25,
            'inventory_source' => 'ecommerce',
        ]);

        $order = Order::create([
            'tracking_number' => 'ORD-TEST-1234',
            'subtotal'        => 5000,
            'total_amount'    => 5000,
            'status'          => 'pending',
            'payment_method'  => 'cash_on_delivery',
            'billing_name'    => 'John Doe',
            'billing_email'   => 'john@example.com',
            'billing_phone'   => '08012345678',
            'billing_address' => '123 Test Street',
        ]);

        OrderItem::create([
            'order_id'         => $order->id,
            'product_id'       => $product->id,
            'quantity'         => 2,
            'price'            => 2500,
            'inventory_source' => 'ecommerce',
        ]);

        $inventoryService = new InventoryService();

        // 1. Deduct order inventory (customer checkout)
        $inventoryService->deductOrderInventory($order);

        $this->assertEquals(23, $product->fresh()->stock);
        $this->assertDatabaseHas('inventory_logs', [
            'product_id'       => $product->id,
            'action_type'      => 'sale',
            'quantity_change'  => -2,
            'previous_stock'   => 25,
            'new_stock'        => 23,
            'reference_type'   => Order::class,
            'reference_id'     => $order->id,
            'reference_number' => 'ORD-TEST-1234',
        ]);

        // 2. Restore order inventory (customer cancellation)
        $inventoryService->restoreOrderInventory($order);

        $this->assertEquals(25, $product->fresh()->stock);
        $this->assertDatabaseHas('inventory_logs', [
            'product_id'       => $product->id,
            'action_type'      => 'cancellation',
            'quantity_change'  => 2,
            'previous_stock'   => 23,
            'new_stock'        => 25,
            'reference_type'   => Order::class,
            'reference_id'     => $order->id,
            'reference_number' => 'ORD-TEST-1234',
        ]);
    }

    public function test_admin_can_record_supplier_purchase_updating_stock_and_cost_price(): void
    {
        $product = Product::create([
            'name'             => 'Supplier Widget',
            'slug'             => 'supplier-widget',
            'url_key'          => 'supplier-widget',
            'sku'              => 'WIDGET-01',
            'product_number'   => 'WIDGET-01',
            'price'            => 4000,
            'cost_price'       => 2000,
            'category_id'      => $this->category->id,
            'status'           => true,
            'stock'            => 5,
            'inventory_source' => 'ecommerce',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.purchases.store'), [
            'purchase_number'   => 'PO-2026-99',
            'supplier_name'     => 'Global Parts Ltd',
            'purchase_date'     => '2026-11-11',
            'payment_status'    => 'paid',
            'notes'             => 'Urgent stock replenishment',
            'items'             => [
                [
                    'product_id' => $product->id,
                    'quantity'   => 15,
                    'unit_cost'  => 2250.00,
                ],
            ],
        ]);

        $purchase = Purchase::first();
        $this->assertNotNull($purchase);
        $response->assertRedirect(route('admin.purchases.index'));

        $this->assertEquals('Global Parts Ltd', $purchase->supplier_name);
        $this->assertEquals(33750.00, (float) $purchase->total_cost);
        $this->assertEquals('paid', $purchase->payment_status);

        // Product stock: 5 + 15 = 20
        $this->assertEquals(20, $product->fresh()->stock);
        // Product cost_price updated to latest unit cost
        $this->assertEquals(2250.00, (float) $product->fresh()->cost_price);

        // Inventory log created
        $this->assertDatabaseHas('inventory_logs', [
            'product_id'       => $product->id,
            'action_type'      => 'purchase',
            'quantity_change'  => 15,
            'previous_stock'   => 5,
            'new_stock'        => 20,
            'reference_type'   => Purchase::class,
            'reference_id'     => $purchase->id,
            'reference_number' => 'PO-2026-99',
            'user_id'          => $this->admin->id,
        ]);
    }

    public function test_cannot_create_purchase_order_with_pos_product(): void
    {
        $posProduct = Product::create([
            'name'             => 'POS Exclusive Soda',
            'slug'             => 'pos-exclusive-soda',
            'url_key'          => 'pos-exclusive-soda',
            'sku'              => 'POS-SODA-01',
            'product_number'   => 'POS-SODA-01',
            'price'            => 500,
            'category_id'      => $this->category->id,
            'status'           => true,
            'stock'            => 10,
            'inventory_source' => 'pos',
            'pos_item_id'      => 888,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.purchases.store'), [
            'supplier_name'  => 'POS Wholesaler',
            'purchase_date'  => '2026-11-11',
            'payment_status' => 'paid',
            'items'          => [
                [
                    'product_id' => $posProduct->id,
                    'quantity'   => 10,
                    'unit_cost'  => 300,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('items.0.product_id');
        $this->assertEquals(10, $posProduct->fresh()->stock);
        $this->assertEquals(0, Purchase::count());
    }

    public function test_admin_can_view_inventory_logs_and_filter(): void
    {
        $product = Product::create([
            'name'             => 'Filterable Item',
            'slug'             => 'filterable-item',
            'url_key'          => 'filterable-item',
            'sku'              => 'FILT-001',
            'product_number'   => 'FILT-001',
            'price'            => 1000,
            'category_id'      => $this->category->id,
            'status'           => true,
            'stock'            => 50,
            'inventory_source' => 'ecommerce',
        ]);

        InventoryLog::create([
            'product_id'      => $product->id,
            'action_type'     => 'adjustment',
            'quantity_change' => 5,
            'previous_stock'  => 45,
            'new_stock'       => 50,
            'reason'          => 'restock',
            'user_id'         => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.logs.index', [
            'action_type' => 'adjustment',
            'search'      => 'Filterable',
            'product_id'  => $product->id,
        ]));

        $response->assertOk();
    }

    public function test_admin_can_search_products_via_ajax_limited_to_25(): void
    {
        // Create 30 products
        for ($i = 1; $i <= 30; $i++) {
            Product::create([
                'name'             => "Diapers Pack {$i}",
                'slug'             => "diapers-pack-{$i}",
                'url_key'          => "diapers-pack-{$i}",
                'sku'              => "DIA-{$i}",
                'product_number'   => "DIA-{$i}",
                'price'            => 1000 + $i,
                'category_id'      => $this->category->id,
                'status'           => true,
                'stock'            => 10,
                'inventory_source' => $i % 2 === 0 ? 'pos' : 'ecommerce',
            ]);
        }

        // Test querying with search term
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.products.search', [
            'q' => 'Diapers',
        ]));

        $response->assertOk();
        $data = $response->json('products');

        $this->assertIsArray($data);
        // Ensure capped at 25 items even when 30 match
        $this->assertCount(25, $data);
        $this->assertEquals('Diapers Pack 1', $data[0]['name']);

        // Test querying specific SKU
        $responseSku = $this->actingAs($this->admin)->get(route('admin.inventory.products.search', [
            'q' => 'DIA-15',
        ]));

        $responseSku->assertOk();
        $skuData = $responseSku->json('products');
        $this->assertCount(1, $skuData);
        $this->assertEquals('Diapers Pack 15', $skuData[0]['name']);
    }

    public function test_admin_can_search_ecommerce_products_for_purchases_via_ajax(): void
    {
        // 10 Ecommerce products + 5 POS products
        for ($i = 1; $i <= 10; $i++) {
            Product::create([
                'name'             => "Supplier Widget Item {$i}",
                'slug'             => "supplier-widget-item-{$i}",
                'url_key'          => "supplier-widget-item-{$i}",
                'sku'              => "WID-ECOM-{$i}",
                'product_number'   => "WID-ECOM-{$i}",
                'price'            => 5000,
                'cost_price'       => 2500,
                'category_id'      => $this->category->id,
                'status'           => true,
                'stock'            => 10,
                'inventory_source' => 'ecommerce',
            ]);
        }

        for ($i = 1; $i <= 5; $i++) {
            Product::create([
                'name'             => "Supplier POS Item {$i}",
                'slug'             => "supplier-pos-item-{$i}",
                'url_key'          => "supplier-pos-item-{$i}",
                'sku'              => "WID-POS-{$i}",
                'product_number'   => "WID-POS-{$i}",
                'price'            => 5000,
                'category_id'      => $this->category->id,
                'status'           => true,
                'stock'            => 10,
                'inventory_source' => 'pos',
                'pos_item_id'      => 700 + $i,
            ]);
        }

        // Test querying with ecommerce_only=true (default for purchases)
        $response = $this->actingAs($this->admin)->get(route('admin.purchases.products.search', [
            'q'              => 'Supplier',
            'ecommerce_only' => 1,
        ]));

        $response->assertOk();
        $products = $response->json('products');
        $this->assertCount(10, $products);
        foreach ($products as $p) {
            $this->assertEquals('ecommerce', $p['inventory_source']);
        }
    }

    public function test_admin_can_filter_purchases_by_product_id(): void
    {
        $productA = Product::create([
            'name'             => 'Purchase Filtered Item A',
            'slug'             => 'purchase-filtered-item-a',
            'url_key'          => 'purchase-filtered-item-a',
            'sku'              => 'PO-FILT-A',
            'product_number'   => 'PO-FILT-A',
            'price'            => 5000,
            'cost_price'       => 2000,
            'category_id'      => $this->category->id,
            'status'           => true,
            'stock'            => 10,
            'inventory_source' => 'ecommerce',
        ]);

        $purchase = Purchase::create([
            'purchase_number'   => 'PO-TEST-100',
            'supplier_name'     => 'Wholesale Direct',
            'purchase_date'     => '2026-11-12',
            'total_cost'        => 20000,
            'total_items_count' => 10,
            'payment_status'    => 'paid',
        ]);

        \App\Models\PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id'  => $productA->id,
            'quantity'    => 10,
            'unit_cost'   => 2000,
            'subtotal'    => 20000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.purchases.index', [
            'product_id' => $productA->id,
        ]));

        $response->assertOk();
    }

    public function test_admin_can_export_inventory_logs_to_csv(): void
    {
        $product = Product::create([
            'name'             => 'Export Ledger Item',
            'slug'             => 'export-ledger-item',
            'url_key'          => 'export-ledger-item',
            'sku'              => 'EXP-001',
            'product_number'   => 'EXP-001',
            'price'            => 2000,
            'category_id'      => $this->category->id,
            'status'           => true,
            'stock'            => 10,
            'inventory_source' => 'ecommerce',
        ]);

        InventoryLog::create([
            'product_id'       => $product->id,
            'action_type'      => 'adjustment',
            'quantity_change'  => 10,
            'previous_stock'   => 0,
            'new_stock'        => 10,
            'reason'           => 'initial_stock',
            'reference_type'   => 'manual_adjustment',
            'reference_number' => 'ADJ-TEST',
            'user_id'          => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.logs.export'));

        $response->assertOk();
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('content-type'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('Date & Time', $content);
        $this->assertStringContainsString('Product Name', $content);
        $this->assertStringContainsString('Export Ledger Item', $content);
        $this->assertStringContainsString('EXP-001', $content);
        $this->assertStringContainsString('initial_stock', $content);
    }
}
