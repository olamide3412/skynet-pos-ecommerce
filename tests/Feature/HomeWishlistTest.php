<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Category;
use App\Models\StoreSetting;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    StoreSetting::set('wishlist_enabled', '1');
    $this->category = Category::create([
        'name' => 'Gadgets',
        'slug' => 'gadgets',
    ]);
    $this->product = Product::create([
        'category_id'    => $this->category->id,
        'name'           => 'Wireless Earbuds',
        'slug'           => 'wireless-earbuds',
        'url_key'        => 'wireless-earbuds',
        'product_number' => 'EARBUD-001',
        'price'          => 15000,
        'status'         => true,
        'stock'          => 10,
        'is_featured'    => true,
    ]);
});

test('guest visiting home page receives empty wishlist_ids', function () {
    $response = $this->get(route('home'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Home')
        ->has('wishlist_ids')
        ->where('wishlist_ids', [])
    );
});

test('authenticated customer visiting home page receives wishlist_ids', function () {
    $customer = Customer::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => bcrypt('password123'),
        'email_verified_at' => now(),
    ]);
    Wishlist::create([
        'customer_id' => $customer->id,
        'product_id' => $this->product->id,
    ]);

    $response = $this->actingAs($customer, 'customer')->get(route('home'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Home')
        ->has('wishlist_ids')
        ->where('wishlist_ids', [$this->product->id])
    );
});

test('authenticated customer can toggle product in wishlist', function () {
    $customer = Customer::create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => bcrypt('password123'),
        'email_verified_at' => now(),
    ]);

    // Add to wishlist
    $response = $this->actingAs($customer, 'customer')
        ->post(route('wishlist.toggle'), [
            'product_id' => $this->product->id,
        ]);

    $response->assertSessionHasNoErrors();
    expect(Wishlist::where('customer_id', $customer->id)->where('product_id', $this->product->id)->exists())->toBeTrue();

    // Toggle again (remove from wishlist)
    $response2 = $this->actingAs($customer, 'customer')
        ->post(route('wishlist.toggle'), [
            'product_id' => $this->product->id,
        ]);

    $response2->assertSessionHasNoErrors();
    expect(Wishlist::where('customer_id', $customer->id)->where('product_id', $this->product->id)->exists())->toBeFalse();
});
