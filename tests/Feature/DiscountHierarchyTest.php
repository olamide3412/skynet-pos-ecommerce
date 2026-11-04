<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductPosUnit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DiscountHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_discount_hierarchy_flash_sale_overrides_regular_discount()
    {
        $category = Category::create([
            'name' => 'Tech',
            'slug' => 'tech',
        ]);

        $product = Product::create([
            'category_id'        => $category->id,
            'name'               => 'Noise Cancelling Headphones',
            'slug'               => 'headphones',
            'url_key'            => 'headphones',
            'product_number'     => 'HP-001',
            'price'              => 50000,
            'discount_price'     => 40000,
            'special_price'      => 30000, // Flash Sale
            'special_price_from' => Carbon::now()->subHour(),
            'special_price_to'   => Carbon::now()->addHours(3),
            'stock'              => 20,
            'status'             => true,
        ]);

        // 1. While within flash sale window: special_price (30,000) wins
        $this->assertTrue($product->is_on_flash_sale);
        $this->assertTrue($product->is_on_sale);
        $this->assertEquals(30000, $product->effective_price);

        // 2. Fast forward time after flash sale ends: reverts back to discount_price (40,000)
        Carbon::setTestNow(Carbon::now()->addHours(4));
        $freshProduct = $product->fresh();

        $this->assertFalse($freshProduct->is_on_flash_sale);
        $this->assertTrue($freshProduct->is_on_sale);
        $this->assertEquals(40000, $freshProduct->effective_price);

        Carbon::setTestNow(); // Reset time
    }

    public function test_pos_multi_units_protect_wholesale_margin_during_flash_sale()
    {
        $category = Category::create([
            'name' => 'Groceries',
            'slug' => 'groceries',
        ]);

        $product = Product::create([
            'category_id'        => $category->id,
            'name'               => 'Cooking Oil',
            'slug'               => 'cooking-oil',
            'url_key'            => 'cooking-oil',
            'product_number'     => 'OIL-001',
            'price'              => 2000,
            'discount_price'     => 1800,
            'special_price'      => 1200, // Flash Sale on base piece
            'special_price_from' => Carbon::now()->subMinutes(10),
            'special_price_to'   => Carbon::now()->addHour(),
            'stock'              => 100,
            'status'             => true,
        ]);

        // Base unit
        $baseUnit = ProductPosUnit::create([
            'product_id'             => $product->id,
            'unit_name'              => 'Piece',
            'unit_kind'              => 'base',
            'quantity_in_base_units' => 1,
            'synced_standard_price'  => 2000,
            'is_enabled_online'      => true,
        ]);

        // Carton unit (12 pieces, wholesale price 22,000)
        $cartonUnit = ProductPosUnit::create([
            'product_id'             => $product->id,
            'unit_name'              => 'Carton',
            'unit_kind'              => 'multi',
            'quantity_in_base_units' => 12,
            'synced_standard_price'  => 22000,
            'is_enabled_online'      => true,
        ]);

        // Base unit gets the Flash Sale price (1,200)
        $this->assertEquals(1200, $product->getEffectivePriceForUnit($baseUnit));

        // Bulk carton unit safely retains its standard POS wholesale price (22,000)
        $this->assertEquals(22000, $product->getEffectivePriceForUnit($cartonUnit));
    }

    public function test_coupon_exclude_sale_items_prevents_conflict_and_double_dipping()
    {
        $category = Category::create([
            'name' => 'Fashion',
            'slug' => 'fashion',
        ]);

        // Item 1: On Flash Sale (5,000 -> 3,000)
        $saleItem = Product::create([
            'category_id'        => $category->id,
            'name'               => 'Flash Sale Watch',
            'slug'               => 'flash-watch',
            'url_key'            => 'flash-watch',
            'product_number'     => 'WTC-001',
            'price'              => 5000,
            'special_price'      => 3000,
            'special_price_from' => Carbon::now()->subMinutes(5),
            'special_price_to'   => Carbon::now()->addHours(2),
            'stock'              => 10,
            'status'             => true,
        ]);

        // Item 2: Regular price (10,000, not on sale)
        $regularItem = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Leather Wallet',
            'slug'           => 'leather-wallet',
            'url_key'        => 'leather-wallet',
            'product_number' => 'WLT-001',
            'price'          => 10000,
            'stock'          => 10,
            'status'         => true,
        ]);

        $customer = Customer::create([
            'name'     => 'Promo Customer',
            'email'    => 'promo@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Add both to cart
        $this->actingAs($customer, 'customer')->post(route('cart.store'), [
            'product_id' => $saleItem->id,
            'quantity'   => 1,
        ]);
        $this->actingAs($customer, 'customer')->post(route('cart.store'), [
            'product_id' => $regularItem->id,
            'quantity'   => 1,
        ]);

        // Subtotal = 3,000 (flash sale) + 10,000 (regular) = 13,000

        // Create coupon with exclude_sale_items = true (10% off regular items only)
        $coupon = Coupon::create([
            'code'               => 'REGULAR10',
            'type'               => 'percent',
            'value'              => 10,
            'is_active'          => true,
            'exclude_sale_items' => true,
        ]);

        // Apply coupon
        $response = $this->actingAs($customer, 'customer')->post(route('coupon.apply'), [
            'code' => 'REGULAR10',
        ]);

        $response->assertSessionHas('success');
        // 10% of 10,000 = 1,000 discount (3,000 flash sale item is NOT double discounted!)
        $this->assertEquals(1000, session('applied_coupon.discount'));
    }
}

