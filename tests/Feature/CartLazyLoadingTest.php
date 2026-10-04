<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartLazyLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_page_loads_with_items_without_lazy_loading_violation(): void
    {
        $category = Category::create(['name' => 'General', 'slug' => 'general']);
        $product = Product::create([
            'name'                 => 'Test Phone',
            'slug'                 => 'test-phone',
            'url_key'              => 'test-phone',
            'category_id'          => $category->id,
            'price'                => 150000,
            'stock'                => 10,
            'status'               => true,
            'inventory_source'     => 'ecommerce',
            'visible_individually' => true,
        ]);

        $sessionId = 'test_session_abc123';
        $cart = Cart::create(['session_id' => $sessionId]);
        CartItem::create([
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'price'      => 150000,
        ]);

        $response = $this->withSession(['_token' => 'dummy'])->get('/cart');
        $response->assertStatus(200);
    }
}
