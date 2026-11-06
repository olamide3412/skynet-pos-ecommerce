<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P3SeoAndPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_xml_returns_valid_structure_with_active_products_and_categories(): void
    {
        config(['app.debug' => false]);

        $category = Category::create([
            'name' => 'Laptops & Computers',
            'slug' => 'laptops-computers',
        ]);

        $activeProduct = Product::create([
            'category_id'    => $category->id,
            'name'           => 'MacBook Pro M3',
            'slug'           => 'macbook-pro-m3',
            'url_key'        => 'macbook-pro-m3',
            'product_number' => 'MBP-001',
            'price'          => 2500000,
            'status'         => true,
            'stock'          => 5,
        ]);

        $inactiveProduct = Product::create([
            'category_id'    => $category->id,
            'name'           => 'Draft Concept Laptop',
            'slug'           => 'draft-concept-laptop',
            'url_key'        => 'draft-concept-laptop',
            'product_number' => 'DRAFT-001',
            'price'          => 100000,
            'status'         => false,
            'stock'          => 0,
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $content = $response->getContent();

        // Must be valid XML
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $content);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $content);

        // Must include home and shop
        $this->assertStringContainsString(url('/'), $content);
        $this->assertStringContainsString(route('shop.index'), $content);

        // Must include active category and active product
        $this->assertStringContainsString($category->slug, $content);
        $this->assertStringContainsString($activeProduct->url_key, $content);

        // Must NOT include inactive product
        $this->assertStringNotContainsString($inactiveProduct->url_key, $content);
    }

    public function test_robots_txt_contains_sitemap_and_disallowed_paths(): void
    {
        config(['app.debug' => false]);
        $response = $this->get('/robots.txt');

        $response->assertStatus(200);
        $response->assertSee('Disallow: /admin/');
        $response->assertSee('Disallow: /checkout/');
        $response->assertSee('Sitemap:');
    }

    public function test_debug_mode_blocks_crawlers_and_rejects_sitemap(): void
    {
        config(['app.debug' => true]);

        // Sitemap must reject with 403 Forbidden
        $sitemapResponse = $this->get('/sitemap.xml');
        $sitemapResponse->assertStatus(403);
        $sitemapResponse->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        // site.xml alias must also reject with 403 Forbidden
        $siteAliasResponse = $this->get('/site.xml');
        $siteAliasResponse->assertStatus(403);

        // robots.txt must disallow all crawlers entirely
        $robotsResponse = $this->get('/robots.txt');
        $robotsResponse->assertStatus(200);
        $robotsResponse->assertSee("User-agent: *\nDisallow: /", false);
        $robotsResponse->assertDontSee('Sitemap:');
        $robotsResponse->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}

