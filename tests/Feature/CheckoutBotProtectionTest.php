<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutBotProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Http::fake([
            '*/reservations' => \Illuminate\Support\Facades\Http::response(['status' => true, 'data' => ['id' => 1]], 200),
        ]);

        StoreSetting::set('shop_enabled', '1');
        StoreSetting::set('guest_checkout_enabled', '1');
        StoreSetting::set('cod_enabled', '1');

        $this->category = Category::create([
            'name' => 'General',
            'slug' => 'general',
        ]);

        $this->product = Product::create([
            'category_id'      => $this->category->id,
            'name'             => 'Test Item',
            'slug'             => 'test-item',
            'url_key'          => 'test-item',
            'price'            => 5000,
            'stock'            => 10,
            'inventory_source' => 'ecommerce',
            'status'           => true,
            'pos_sync_status'  => 'synced',
        ]);
    }

    public function test_bot_filling_checkout_honeypot_is_rejected(): void
    {
        $customer = Customer::create([
            'name'     => 'Bot Customer',
            'email'    => 'bot@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);

        $this->actingAs($customer, 'customer')->post(route('cart.store'), [
            'product_id' => $this->product->id,
            'quantity'   => 1,
        ]);

        $response = $this->actingAs($customer, 'customer')->post(route('checkout.process'), [
            'name'                 => 'Spam Bot',
            'email'                => 'bot@example.com',
            'phone'                => '08012345678',
            'address'              => '123 Fake Street',
            'gateway'              => 'pod',
            'delivery_method'      => 'delivery',
            'b_hp_billing_company' => 'Bot Automation Corp', // Honeypot filled
            '_rendered_at'         => (microtime(true) - 5) * 1000,
        ]);

        $response->assertRedirect(route('cart.index'));
        $this->assertDatabaseMissing('orders', ['billing_email' => 'bot@example.com']);
    }

    public function test_instantaneous_script_checkout_is_rejected(): void
    {
        $customer = Customer::create([
            'name'     => 'Fast Customer',
            'email'    => 'fastscript@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);

        $this->actingAs($customer, 'customer')->post(route('cart.store'), [
            'product_id' => $this->product->id,
            'quantity'   => 1,
        ]);

        $response = $this->actingAs($customer, 'customer')->post(route('checkout.process'), [
            'name'                 => 'Fast Script',
            'email'                => 'fastscript@example.com',
            'phone'                => '08012345678',
            'address'              => '123 Fast Street',
            'gateway'              => 'pod',
            'delivery_method'      => 'delivery',
            'b_hp_billing_company' => '',
            '_rendered_at'         => microtime(true) * 1000, // 0ms elapsed
        ]);

        $response->assertRedirect(route('cart.index'));
        $this->assertDatabaseMissing('orders', ['billing_email' => 'fastscript@example.com']);
    }

    public function test_pod_order_cap_blocks_excessive_unpaid_pod_orders(): void
    {
        $customer = Customer::create([
            'name'     => 'Repeat User',
            'email'    => 'repeat@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);

        // Create 3 existing unpaid POD orders for this phone/email
        for ($i = 0; $i < 3; $i++) {
            Order::create([
                'status'          => 'pending',
                'payment_method'  => 'pod',
                'total_amount'    => 5000,
                'billing_name'    => 'Repeat User',
                'billing_email'   => 'repeat@example.com',
                'billing_phone'   => '08099998888',
                'billing_address' => 'Repeat Address',
                'delivery_method' => 'delivery',
                'tracking_number' => 'SKY-TRK-' . $i,
            ]);
        }

        $this->actingAs($customer, 'customer')->post(route('cart.store'), [
            'product_id' => $this->product->id,
            'quantity'   => 1,
        ]);

        $response = $this->actingAs($customer, 'customer')->post(route('checkout.process'), [
            'name'                 => 'Repeat User',
            'email'                => 'repeat@example.com',
            'phone'                => '08099998888',
            'address'              => 'Repeat Address',
            'gateway'              => 'pod',
            'delivery_method'      => 'delivery',
            'b_hp_billing_company' => '',
            '_rendered_at'         => (microtime(true) - 6) * 1000, // Legitimate time
        ]);

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHas('warning');

        // Confirm 4th POD order was NOT created
        $this->assertEquals(3, Order::where('billing_email', 'repeat@example.com')->count());
    }

    public function test_legitimate_human_order_succeeds(): void
    {
        $customer = Customer::create([
            'name'     => 'Good Customer',
            'email'    => 'good@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);

        $this->actingAs($customer, 'customer')->post(route('cart.store'), [
            'product_id' => $this->product->id,
            'quantity'   => 1,
        ]);

        $response = $this->actingAs($customer, 'customer')->post(route('checkout.process'), [
            'name'                 => 'Good Customer',
            'email'                => 'good@example.com',
            'phone'                => '08077776666',
            'address'              => '77 Real Crescent, Warri',
            'gateway'              => 'pod',
            'delivery_method'      => 'delivery',
            'b_hp_billing_company' => '',
            '_rendered_at'         => (microtime(true) - 5) * 1000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('orders', [
            'billing_email'  => 'good@example.com',
            'payment_method' => 'pod',
        ]);
    }
}
