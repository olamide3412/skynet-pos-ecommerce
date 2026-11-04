<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SearchLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SearchLoggingAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_suggestions_logs_query(): void
    {
        $category = Category::create(['name' => 'Computers', 'slug' => 'computers']);
        Product::create([
            'category_id'    => $category->id,
            'name'           => 'MacBook Pro 16',
            'slug'           => 'macbook-pro-16',
            'url_key'        => 'macbook-pro-16',
            'product_number' => 'MBP-016',
            'price'          => 1200000,
            'status'         => true,
            'stock'          => 5,
        ]);

        $response = $this->getJson(route('search.suggestions', ['q' => 'MacBook']));
        $response->assertStatus(200);

        $this->assertDatabaseHas('search_logs', [
            'normalized_query' => 'macbook',
            'results_count'    => 1,
        ]);
    }

    public function test_shop_index_search_logs_query(): void
    {
        $category = Category::create(['name' => 'Perfumes', 'slug' => 'perfumes']);
        Product::create([
            'category_id'    => $category->id,
            'name'           => 'Dior Sauvage',
            'slug'           => 'dior-sauvage',
            'url_key'        => 'dior-sauvage',
            'product_number' => 'DIOR-001',
            'price'          => 85000,
            'status'         => true,
            'stock'          => 10,
        ]);

        $response = $this->get(route('shop.index', ['search' => 'Dior Sauvage']));
        $response->assertStatus(200);

        $this->assertDatabaseHas('search_logs', [
            'normalized_query' => 'dior sauvage',
            'results_count'    => 1,
        ]);
    }

    public function test_home_page_renders_most_searched_products_section(): void
    {
        $category = Category::create(['name' => 'Audio', 'slug' => 'audio']);
        $product = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Sony WH-1000XM5',
            'slug'           => 'sony-wh-1000xm5',
            'url_key'        => 'sony-wh-1000xm5',
            'product_number' => 'SONY-005',
            'price'          => 350000,
            'status'         => true,
            'stock'          => 10,
        ]);

        // Seed 5 searches for 'Sony'
        for ($i = 0; $i < 5; $i++) {
            SearchLog::record('Sony', 1);
        }

        $response = $this->get(route('home'));
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('most_searched')
            ->has('top_search_keywords')
            ->where('top_search_keywords.0', 'sony')
        );
    }

    public function test_search_trending_returns_top_five_searched_items(): void
    {
        $category = Category::create(['name' => 'Phones', 'slug' => 'phones']);
        Product::create([
            'category_id'    => $category->id,
            'name'           => 'iPhone 15 Pro Max',
            'slug'           => 'iphone-15-pro-max',
            'url_key'        => 'iphone-15-pro-max',
            'product_number' => 'IPHONE-15',
            'price'          => 1800000,
            'status'         => true,
            'stock'          => 10,
        ]);

        SearchLog::record('iPhone', 1);

        $response = $this->getJson(route('search.trending'));
        $response->assertStatus(200)
            ->assertJsonStructure([
                'keywords',
                'products',
            ]);

        $this->assertNotEmpty($response->json('products'));
    }

    public function test_admin_can_view_search_analytics(): void
    {
        $this->withoutVite();

        $admin = User::factory()->create([
            'role' => \App\Enums\RoleEnums::Administrator->value,
        ]);

        SearchLog::record('Samsung Galaxy', 2);
        SearchLog::record('Rolex Watch', 0); // Zero results

        $response = $this->actingAs($admin, 'web')->get(route('admin.reports.search-analytics'));
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/SearchAnalytics')
            ->has('top_searches.data')
            ->has('zero_result_searches')
            ->has('metrics')
            ->where('metrics.total_searches', 2)
            ->where('metrics.zero_results', 1)
        );
    }
}
