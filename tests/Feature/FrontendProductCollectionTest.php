<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FrontendProductCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_filter_featured_products(): void
    {
        $category = Category::create(['name' => 'Phones', 'slug' => 'phones']);

        $featured = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Featured iPhone',
            'slug'           => 'featured-iphone',
            'url_key'        => 'featured-iphone',
            'product_number' => 'IPHONE-001',
            'price'          => 500000,
            'status'         => true,
            'stock'          => 10,
            'is_featured'    => true,
        ]);

        $regular = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Regular Phone',
            'slug'           => 'regular-phone',
            'url_key'        => 'regular-phone',
            'product_number' => 'PHONE-002',
            'price'          => 200000,
            'status'         => true,
            'stock'          => 10,
            'is_featured'    => false,
        ]);

        $response = $this->get(route('shop.index', ['collection' => 'featured']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Frontend/Products/Index')
            ->where('filters.collection', 'featured')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Featured iPhone')
        );
    }

    public function test_can_filter_new_arrivals(): void
    {
        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);

        $newProduct = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Brand New Gadget',
            'slug'           => 'brand-new-gadget',
            'url_key'        => 'brand-new-gadget',
            'product_number' => 'GADGET-001',
            'price'          => 150000,
            'status'         => true,
            'stock'          => 5,
            'is_new'         => true,
        ]);

        $oldProduct = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Older Gadget',
            'slug'           => 'older-gadget',
            'url_key'        => 'older-gadget',
            'product_number' => 'GADGET-002',
            'price'          => 120000,
            'status'         => true,
            'stock'          => 5,
            'is_new'         => false,
        ]);

        $response = $this->get(route('shop.index', ['collection' => 'new-arrivals']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Frontend/Products/Index')
            ->where('filters.collection', 'new-arrivals')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Brand New Gadget')
        );
    }

    public function test_can_filter_best_sellers_sorted_by_sales_volume(): void
    {
        $category = Category::create(['name' => 'Fashion', 'slug' => 'fashion']);

        $topSeller = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Top Selling Shoes',
            'slug'           => 'top-selling-shoes',
            'url_key'        => 'top-selling-shoes',
            'product_number' => 'SHOE-001',
            'price'          => 45000,
            'status'         => true,
            'stock'          => 20,
        ]);

        $lowSeller = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Low Selling Shoes',
            'slug'           => 'low-selling-shoes',
            'url_key'        => 'low-selling-shoes',
            'product_number' => 'SHOE-002',
            'price'          => 35000,
            'status'         => true,
            'stock'          => 20,
        ]);

        $order = Order::create([
            'guest_email'    => 'john@example.com',
            'total_amount'   => 500000,
            'status'         => 'completed',
            'payment_method' => 'card',
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $topSeller->id,
            'price'      => $topSeller->price,
            'quantity'   => 15,
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $lowSeller->id,
            'price'      => $lowSeller->price,
            'quantity'   => 2,
        ]);

        $response = $this->get(route('shop.index', ['collection' => 'best-sellers']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Frontend/Products/Index')
            ->where('filters.collection', 'best-sellers')
            ->where('products.data.0.name', 'Top Selling Shoes')
            ->where('products.data.1.name', 'Low Selling Shoes')
        );
    }

    public function test_can_sort_products_alphabetically_a_to_z(): void
    {
        $category = Category::create(['name' => 'General', 'slug' => 'general']);

        Product::create([
            'category_id'    => $category->id,
            'name'           => 'Zebra Stripes Mug',
            'slug'           => 'zebra-stripes-mug',
            'url_key'        => 'zebra-stripes-mug',
            'product_number' => 'MUG-001',
            'price'          => 3000,
            'status'         => true,
            'stock'          => 10,
        ]);

        Product::create([
            'category_id'    => $category->id,
            'name'           => 'Apple Green Bottle',
            'slug'           => 'apple-green-bottle',
            'url_key'        => 'apple-green-bottle',
            'product_number' => 'BOT-001',
            'price'          => 5000,
            'status'         => true,
            'stock'          => 10,
        ]);

        $response = $this->get(route('shop.index', ['sort' => 'name_asc']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Frontend/Products/Index')
            ->where('filters.sort', 'name_asc')
            ->where('products.data.0.name', 'Apple Green Bottle')
            ->where('products.data.1.name', 'Zebra Stripes Mug')
        );

        $responseDesc = $this->get(route('shop.index', ['sort' => 'name_desc']));
        $responseDesc->assertStatus(200);
        $responseDesc->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'name_desc')
            ->where('products.data.0.name', 'Zebra Stripes Mug')
            ->where('products.data.1.name', 'Apple Green Bottle')
        );
    }

    public function test_can_sort_products_by_price_low_to_high_and_high_to_low(): void
    {
        $category = Category::create(['name' => 'General2', 'slug' => 'general-2']);

        Product::create([
            'category_id'    => $category->id,
            'name'           => 'Expensive Item',
            'slug'           => 'expensive-item',
            'url_key'        => 'expensive-item',
            'product_number' => 'EXP-001',
            'price'          => 80000,
            'status'         => true,
            'stock'          => 10,
        ]);

        Product::create([
            'category_id'    => $category->id,
            'name'           => 'Discounted Item',
            'slug'           => 'discounted-item',
            'url_key'        => 'discounted-item',
            'product_number' => 'DISC-001',
            'price'          => 100000,
            'discount_price' => 20000,
            'status'         => true,
            'stock'          => 10,
        ]);

        $responseAsc = $this->get(route('shop.index', ['sort' => 'price_asc']));
        $responseAsc->assertStatus(200);
        $responseAsc->assertInertia(fn (Assert $page) => $page
            ->component('Frontend/Products/Index')
            ->where('filters.sort', 'price_asc')
            ->where('products.data.0.name', 'Discounted Item')
            ->where('products.data.1.name', 'Expensive Item')
        );

        $responseDesc = $this->get(route('shop.index', ['sort' => 'price_desc']));
        $responseDesc->assertStatus(200);
        $responseDesc->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'price_desc')
            ->where('products.data.0.name', 'Expensive Item')
            ->where('products.data.1.name', 'Discounted Item')
        );
    }
}
