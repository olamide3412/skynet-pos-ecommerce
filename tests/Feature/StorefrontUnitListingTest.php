<?php

namespace Tests\Feature;

use App\Enums\RoleEnums;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPosUnit;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontUnitListingTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Groceries',
            'slug' => 'groceries',
        ]);
    }

    public function test_when_split_setting_is_off_products_appear_as_single_card(): void
    {
        StoreSetting::set('split_selling_units_in_listing', '0');

        $product = Product::create([
            'name' => 'Milo Chocolate Drink',
            'slug' => 'milo-chocolate-drink',
            'url_key' => 'milo-chocolate-drink',
            'price' => 1000,
            'stock' => 100,
            'inventory_source' => 'ecommerce',
            'category_id' => $this->category->id,
            'status' => true,
        ]);

        ProductPosUnit::create([
            'product_id' => $product->id,
            'unit_kind' => 'base',
            'unit_name' => 'Single Tin',
            'quantity_in_base_units' => 1.0,
            'synced_standard_price' => 1000,
            'is_enabled_online' => true,
            'show_in_listing' => true,
        ]);

        ProductPosUnit::create([
            'product_id' => $product->id,
            'unit_kind' => 'additional',
            'unit_name' => 'Half Roll',
            'quantity_in_base_units' => 5.0,
            'synced_standard_price' => 4500,
            'is_enabled_online' => true,
            'show_in_listing' => true,
        ]);

        $response = $this->get(route('shop.index'));
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Frontend/Products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Milo Chocolate Drink')
        );
    }

    public function test_when_split_setting_is_on_selling_units_expand_into_individual_cards(): void
    {
        StoreSetting::set('split_selling_units_in_listing', '1');
        StoreSetting::set('split_unit_out_of_stock_behavior', 'show_out_of_stock');

        $product = Product::create([
            'name' => 'Milo Refill',
            'slug' => 'milo-refill',
            'url_key' => 'milo-refill',
            'price' => 800,
            'stock' => 50,
            'image' => 'products/milo-base.webp',
            'inventory_source' => 'ecommerce',
            'category_id' => $this->category->id,
            'status' => true,
        ]);

        ProductPosUnit::create([
            'product_id' => $product->id,
            'unit_kind' => 'base',
            'unit_name' => 'Single Pack',
            'quantity_in_base_units' => 1.0,
            'synced_standard_price' => 800,
            'is_enabled_online' => true,
            'show_in_listing' => true,
        ]);

        $halfRoll = ProductPosUnit::create([
            'product_id' => $product->id,
            'unit_kind' => 'additional',
            'unit_name' => 'Half Roll',
            'quantity_in_base_units' => 5.0,
            'synced_standard_price' => 3800,
            'image' => 'products/milo-half-roll.webp',
            'is_enabled_online' => true,
            'show_in_listing' => true,
        ]);

        $oneRoll = ProductPosUnit::create([
            'product_id' => $product->id,
            'unit_kind' => 'additional',
            'unit_name' => '1 Roll',
            'quantity_in_base_units' => 10.0,
            'synced_standard_price' => 7400,
            'image' => null, // fallback to product image
            'is_enabled_online' => true,
            'show_in_listing' => true,
        ]);

        $response = $this->get(route('shop.index'));
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Frontend/Products/Index')
            ->has('products.data', 3)
            // Card 1: Base product
            ->where('products.data.0.name', 'Milo Refill')
            ->where('products.data.0.price', 800)
            ->where('products.data.0.stock', 50)
            ->where('products.data.0.is_unit_listing', false)
            ->where('products.data.0.image', 'products/milo-base.webp')
            // Card 2: Half Roll x5
            ->where('products.data.1.name', 'Milo Refill Half Roll x5')
            ->where('products.data.1.price', 3800)
            ->where('products.data.1.stock', 10) // 50 / 5 = 10
            ->where('products.data.1.is_unit_listing', true)
            ->where('products.data.1.image', 'products/milo-half-roll.webp')
            ->where('products.data.1.unit_id', $halfRoll->id)
            // Card 3: 1 Roll x10 with fallback image
            ->where('products.data.2.name', 'Milo Refill 1 Roll x10')
            ->where('products.data.2.price', 7400)
            ->where('products.data.2.stock', 5) // 50 / 10 = 5
            ->where('products.data.2.is_unit_listing', true)
            ->where('products.data.2.image', 'products/milo-base.webp')
            ->where('products.data.2.unit_id', $oneRoll->id)
        );
    }

    public function test_units_with_show_in_listing_false_or_disabled_are_skipped(): void
    {
        StoreSetting::set('split_selling_units_in_listing', '1');

        $product = Product::create([
            'name' => 'Peak Milk',
            'slug' => 'peak-milk',
            'url_key' => 'peak-milk',
            'price' => 500,
            'stock' => 100,
            'inventory_source' => 'ecommerce',
            'category_id' => $this->category->id,
            'status' => true,
        ]);

        // Unit 1: show_in_listing = false
        ProductPosUnit::create([
            'product_id' => $product->id,
            'unit_kind' => 'additional',
            'unit_name' => 'Secret Carton',
            'quantity_in_base_units' => 24.0,
            'synced_standard_price' => 11000,
            'is_enabled_online' => true,
            'show_in_listing' => false,
        ]);

        // Unit 2: is_enabled_online = false
        ProductPosUnit::create([
            'product_id' => $product->id,
            'unit_kind' => 'additional',
            'unit_name' => 'Draft Pack',
            'quantity_in_base_units' => 6.0,
            'synced_standard_price' => 2900,
            'is_enabled_online' => false,
            'show_in_listing' => true,
        ]);

        $response = $this->get(route('shop.index'));
        $response->assertStatus(200);

        // Only base card should appear
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Frontend/Products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Peak Milk')
        );
    }

    public function test_zero_stock_unit_behavior_hides_card_when_configured(): void
    {
        StoreSetting::set('split_selling_units_in_listing', '1');
        StoreSetting::set('split_unit_out_of_stock_behavior', 'hide');

        $product = Product::create([
            'name' => 'Dano Milk',
            'slug' => 'dano-milk',
            'url_key' => 'dano-milk',
            'price' => 600,
            'stock' => 8, // Stock is 8
            'inventory_source' => 'ecommerce',
            'category_id' => $this->category->id,
            'status' => true,
        ]);

        // Unit requires 10 items per roll -> 8 / 10 = 0 packages possible
        ProductPosUnit::create([
            'product_id' => $product->id,
            'unit_kind' => 'additional',
            'unit_name' => '1 Roll',
            'quantity_in_base_units' => 10.0,
            'synced_standard_price' => 5800,
            'is_enabled_online' => true,
            'show_in_listing' => true,
        ]);

        $response = $this->get(route('shop.index'));
        $response->assertStatus(200);

        // Unit card with 0 stock should be hidden when out of stock behavior is 'hide'
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Frontend/Products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Dano Milk')
        );
    }

    public function test_searching_unit_label_matches_and_returns_unit_cards(): void
    {
        StoreSetting::set('split_selling_units_in_listing', '1');

        $product = Product::create([
            'name' => 'Golden Penny Spaghetti',
            'slug' => 'golden-penny-spaghetti',
            'url_key' => 'golden-penny-spaghetti',
            'price' => 700,
            'stock' => 100,
            'inventory_source' => 'ecommerce',
            'category_id' => $this->category->id,
            'status' => true,
        ]);

        ProductPosUnit::create([
            'product_id' => $product->id,
            'unit_kind' => 'additional',
            'unit_name' => 'Carton of 20',
            'online_label' => 'Super Carton',
            'quantity_in_base_units' => 20.0,
            'synced_standard_price' => 13500,
            'is_enabled_online' => true,
            'show_in_listing' => true,
        ]);

        // Search for "Super Carton"
        $response = $this->get(route('shop.index', ['search' => 'Super Carton']));
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Frontend/Products/Index')
            ->where('products.data.0.name', 'Golden Penny Spaghetti Super Carton x20')
        );
    }

    public function test_product_detail_page_receives_preselected_unit_id(): void
    {
        $product = Product::create([
            'name' => 'Indomie Instant Noodles',
            'slug' => 'indomie-instant-noodles',
            'url_key' => 'indomie-instant-noodles',
            'price' => 250,
            'stock' => 200,
            'inventory_source' => 'ecommerce',
            'category_id' => $this->category->id,
            'status' => true,
        ]);

        $cartonUnit = ProductPosUnit::create([
            'product_id' => $product->id,
            'unit_kind' => 'additional',
            'unit_name' => 'Carton of 40',
            'quantity_in_base_units' => 40.0,
            'synced_standard_price' => 9500,
            'is_enabled_online' => true,
            'show_in_listing' => true,
        ]);

        $response = $this->get(route('shop.show', ['url_key' => $product->url_key, 'unit' => $cartonUnit->id]));
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Frontend/Products/Show')
            ->where('preselected_unit_id', $cartonUnit->id)
        );
    }

    public function test_admin_can_save_split_selling_units_settings(): void
    {
        $admin = User::factory()->create([
            'role' => RoleEnums::SuperAdministrator->value,
        ]);

        $payload = [
            'split_selling_units_in_listing' => true,
            'split_unit_out_of_stock_behavior' => 'hide',
            'order_tracking_enabled' => true,
            'customer_registration_enabled' => true,
            'shop_enabled' => true,
            'guest_checkout_enabled' => true,
            'reviews_enabled' => true,
            'wishlist_enabled' => true,
            'show_cart_total_header' => true,
            'show_stock_level_default' => true,
            'pos_brand_sync_overwrite' => false,
            'turnstile_enabled' => false,
            'google_auth_enabled' => false,
            'facebook_auth_enabled' => false,
            'paystack_enabled' => false,
            'bank_transfer_enabled' => false,
            'delivery_pay_enabled' => false,
            'pickup_pay_enabled' => false,
            'stock_alert_enabled' => false,
            'abandoned_cart_reminder_enabled' => false,
        ];

        $response = $this->actingAs($admin, 'web')->put(route('admin.settings.update'), $payload);
        $response->assertSessionHasNoErrors();

        $this->assertEquals('1', StoreSetting::get('split_selling_units_in_listing'));
        $this->assertEquals('hide', StoreSetting::get('split_unit_out_of_stock_behavior'));
    }
}
