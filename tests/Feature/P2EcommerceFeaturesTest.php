<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ShippingZone;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class P2EcommerceFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        StoreSetting::set('shop_enabled', '1');
        StoreSetting::set('guest_checkout_enabled', '1');
        StoreSetting::set('reviews_enabled', '1');
        StoreSetting::set('bank_transfer_enabled', '1');
        StoreSetting::set('shipping_enabled', '1');
    }

    public function test_customer_can_request_forgot_password_link(): void
    {
        $customer = Customer::create([
            'name'     => 'Jane Doe',
            'email'    => 'jane@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post(route('password.email'), [
            'email' => 'jane@example.com',
        ]);

        $response->assertSessionHas('status');
    }

    public function test_customer_can_update_profile_and_password(): void
    {
        $customer = Customer::create([
            'name'     => 'John Doe',
            'email'    => 'john@example.com',
            'phone'    => '08012345678',
            'password' => Hash::make('old_password'),
        ]);

        // Update profile
        $response = $this->actingAs($customer, 'customer')->put(route('customer.profile.update'), [
            'name'  => 'John Updated',
            'email' => 'john_new@example.com',
            'phone' => '08099998888',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('customers', [
            'id'    => $customer->id,
            'name'  => 'John Updated',
            'email' => 'john_new@example.com',
        ]);

        // Update password
        $pwResponse = $this->actingAs($customer->fresh(), 'customer')->put(route('customer.password.update'), [
            'current_password'      => 'old_password',
            'password'              => 'new_secret_password',
            'password_confirmation' => 'new_secret_password',
        ]);

        $pwResponse->assertSessionHas('success');
        $this->assertTrue(Hash::check('new_secret_password', $customer->fresh()->password));
    }

    public function test_customer_can_manage_saved_addresses_and_set_default(): void
    {
        $customer = Customer::create([
            'name'     => 'Alice Smith',
            'email'    => 'alice@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Store first address
        $response = $this->actingAs($customer, 'customer')->post(route('customer.addresses.store'), [
            'name'       => 'Alice Home',
            'phone'      => '08011112222',
            'address'    => '10 Marine Road',
            'city'       => 'Victoria Island',
            'state'      => 'Lagos',
            'is_default' => true,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('addresses', [
            'customer_id' => $customer->id,
            'address'     => '10 Marine Road',
            'is_default'  => 1,
        ]);

        $firstAddress = Address::where('customer_id', $customer->id)->first();

        // Store second address as default, ensuring previous default is cleared
        $this->actingAs($customer, 'customer')->post(route('customer.addresses.store'), [
            'name'       => 'Alice Office',
            'phone'      => '08033334444',
            'address'    => '25 Broad Street',
            'city'       => 'Marina',
            'state'      => 'Lagos',
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('addresses', [
            'id'         => $firstAddress->id,
            'is_default' => 0,
        ]);
    }

    public function test_customer_can_submit_product_review(): void
    {
        $category = Category::create(['name' => 'Phones', 'slug' => 'phones']);
        $product = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Flagship Smartphone',
            'slug'           => 'flagship-smartphone',
            'url_key'        => 'flagship-smartphone',
            'product_number' => 'PH-001',
            'sku'            => 'SKU-PH001',
            'price'          => 250000,
            'stock'          => 10,
            'status'         => true,
        ]);

        $customer = Customer::create([
            'name'     => 'Reviewer User',
            'email'    => 'reviewer@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->actingAs($customer, 'customer')->post(route('products.reviews.store', $product->id), [
            'rating'  => 5,
            'comment' => 'Exceptional build quality and speedy delivery!',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('product_reviews', [
            'product_id'  => $product->id,
            'customer_id' => $customer->id,
            'rating'      => 5,
        ]);

        $this->assertEquals(5.0, $product->fresh()->average_rating);
    }

    public function test_coupon_can_be_applied_and_removed_on_cart(): void
    {
        $coupon = Coupon::create([
            'code'             => 'SAVE10',
            'type'             => 'percent',
            'value'            => 10,
            'min_order_amount' => 5000,
            'is_active'        => true,
        ]);

        $customer = Customer::create([
            'name'     => 'Cart Shopper',
            'email'    => 'shopper@example.com',
            'password' => Hash::make('password123'),
        ]);

        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        $product = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Wireless Headphones',
            'slug'           => 'wireless-headphones',
            'url_key'        => 'wireless-headphones',
            'product_number' => 'WH-001',
            'sku'            => 'SKU-WH001',
            'price'          => 20000,
            'stock'          => 15,
            'status'         => true,
        ]);

        // Add to cart as authenticated customer
        $this->actingAs($customer, 'customer')->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        // Apply coupon
        $response = $this->actingAs($customer, 'customer')->post(route('coupon.apply'), [
            'code' => 'SAVE10',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('SAVE10', session('applied_coupon.code'));
        $this->assertEquals(2000.0, (float) session('applied_coupon.discount'));

        // Remove coupon
        $removeResponse = $this->actingAs($customer, 'customer')->post(route('coupon.remove'));
        $removeResponse->assertSessionHas('info');
        $this->assertNull(session('applied_coupon'));
    }

    public function test_checkout_supports_direct_bank_transfer_order_placement(): void
    {
        $category = Category::create(['name' => 'Computers', 'slug' => 'computers']);
        $product = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Laptop Charger',
            'slug'           => 'laptop-charger',
            'url_key'        => 'laptop-charger',
            'product_number' => 'CH-001',
            'sku'            => 'SKU-CH001',
            'price'          => 12000,
            'stock'          => 20,
            'status'         => true,
        ]);

        $zone = ShippingZone::create([
            'state'          => 'Lagos',
            'fee'            => 1500,
            'estimated_days' => '1 - 2 Days',
            'is_active'      => true,
        ]);

        $customer = Customer::create([
            'name'     => 'Bank Customer',
            'email'    => 'bank_user@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Add to cart
        $this->actingAs($customer, 'customer')->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $checkoutResponse = $this->actingAs($customer, 'customer')->post(route('checkout.process'), [
            'name'             => 'Bank Customer',
            'email'            => 'bank_user@example.com',
            'phone'            => '08022223333',
            'address'          => '15 Lekki Expressway, Lagos',
            'delivery_method'  => 'delivery',
            'shipping_zone_id' => $zone->id,
            'gateway'          => 'bank_transfer',
        ]);

        $checkoutResponse->assertRedirect();
        $this->assertDatabaseHas('orders', [
            'billing_email'  => 'bank_user@example.com',
            'payment_method' => 'bank_transfer',
            'status'         => 'pending',
        ]);
        $this->assertDatabaseHas('payments', [
            'provider' => 'bank_transfer',
            'status'   => 'pending',
        ]);
    }

    public function test_checkout_supports_pod_payment_on_delivery_order_placement()
    {
        StoreSetting::set('cod_enabled', '1');

        $category = Category::create([
            'name' => 'Phones',
            'slug' => 'phones',
        ]);

        $product = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Smartphone X',
            'slug'           => 'smartphone-x',
            'url_key'        => 'smartphone-x',
            'product_number' => 'PH-001',
            'price'          => 20000,
            'stock'          => 5,
            'status'         => true,
        ]);

        $customer = Customer::create([
            'name'     => 'POD Customer',
            'email'    => 'pod_user@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Add to cart
        $this->actingAs($customer, 'customer')->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $checkoutResponse = $this->actingAs($customer, 'customer')->post(route('checkout.process'), [
            'name'             => 'POD Customer',
            'email'            => 'pod_user@example.com',
            'phone'            => '08099998888',
            'address'          => '42 Marina, Lagos',
            'delivery_method'  => 'delivery',
            'gateway'          => 'pod',
        ]);

        $checkoutResponse->assertRedirect();
        $this->assertDatabaseHas('orders', [
            'billing_email'  => 'pod_user@example.com',
            'payment_method' => 'pod',
            'status'         => 'processing',
        ]);
        $this->assertDatabaseHas('payments', [
            'provider' => 'pod',
            'status'   => 'pending',
        ]);
    }
}
