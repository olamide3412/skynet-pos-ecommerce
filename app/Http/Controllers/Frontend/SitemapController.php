<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Generate dynamic XML sitemap of all active products, categories, and core routes.
     */
    public function index(): Response
    {
        if (config('app.debug', false)) {
            return response("Sitemap is disabled in debug/demo mode.\n", 403, [
                'Content-Type'  => 'text/plain; charset=utf-8',
                'X-Robots-Tag'  => 'noindex, nofollow, noarchive',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);
        }

        $baseUrl = url('/');
        $today = now()->format('Y-m-d');

        $staticPages = [
            [
                'loc'        => $baseUrl,
                'lastmod'    => $today,
                'changefreq' => 'daily',
                'priority'   => '1.0',
            ],
            [
                'loc'        => route('shop.index'),
                'lastmod'    => $today,
                'changefreq' => 'daily',
                'priority'   => '0.9',
            ],
            [
                'loc'        => route('order.track'),
                'lastmod'    => $today,
                'changefreq' => 'monthly',
                'priority'   => '0.5',
            ],
        ];

        // Active Categories
        $categories = Category::query()
            ->latest('updated_at')
            ->get()
            ->map(function ($cat) use ($today) {
                return [
                    'loc'        => route('shop.index', ['category' => $cat->slug]),
                    'lastmod'    => $cat->updated_at ? $cat->updated_at->format('Y-m-d') : $today,
                    'changefreq' => 'weekly',
                    'priority'   => '0.8',
                ];
            });

        // Active Published Products only
        $products = Product::where('status', true)
            ->whereNotNull('url_key')
            ->latest('updated_at')
            ->get(['url_key', 'updated_at'])
            ->map(function ($product) use ($today) {
                return [
                    'loc'        => route('shop.show', $product->url_key),
                    'lastmod'    => $product->updated_at ? $product->updated_at->format('Y-m-d') : $today,
                    'changefreq' => 'weekly',
                    'priority'   => '0.8',
                ];
            });

        $urls = array_merge($staticPages, $categories->toArray(), $products->toArray());

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($urls as $url) {
            $loc = htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8');
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$loc}</loc>\n";
            $xml .= "    <lastmod>{$url['lastmod']}</lastmod>\n";
            $xml .= "    <changefreq>{$url['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$url['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";

        return response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    /**
     * Serve standard robots.txt referencing the dynamic sitemap.
     */
    public function robots(): Response
    {
        if (config('app.debug', false)) {
            $content = "User-agent: *\nDisallow: /\n";

            return response($content, 200, [
                'Content-Type'  => 'text/plain; charset=utf-8',
                'X-Robots-Tag'  => 'noindex, nofollow, noarchive',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);
        }

        $sitemapUrl = url('/sitemap.xml');

        $content = "User-agent: *\n";
        $content .= "Disallow: /admin/\n";
        $content .= "Disallow: /checkout/\n";
        $content .= "Disallow: /my-orders\n";
        $content .= "Disallow: /my-account\n";
        $content .= "Disallow: /my-addresses\n";
        $content .= "Disallow: /wishlist\n";
        $content .= "Disallow: /cart\n";
        $content .= "Allow: /\n\n";
        $content .= "Sitemap: {$sitemapUrl}\n";

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
