<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DiscountController extends Controller
{
    /**
     * Display a listing of all products with active discounts or flash sales.
     */
    public function index(Request $request)
    {
        $tab = $request->input('tab', 'all');
        $search = trim($request->input('search', ''));
        $categoryId = $request->input('category_id');

        $now = Carbon::now();

        // Base query: Products that have ANY discount or special price configured
        $query = Product::with(['category'])
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNotNull('discount_price')->where('discount_price', '>', 0);
                })->orWhere(function ($sub) {
                    $sub->whereNotNull('special_price')->where('special_price', '>', 0);
                });
            });

        // Search filter
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('product_number', 'like', "%{$search}%");
            });
        }

        // Category filter
        if (!empty($categoryId)) {
            $query->where('category_id', $categoryId);
        }

        // Tab filter
        if ($tab === 'active_flash') {
            $query->whereNotNull('special_price')
                ->where('special_price', '>', 0)
                ->where(function ($q) use ($now) {
                    $q->whereNull('special_price_from')->orWhere('special_price_from', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('special_price_to')->orWhere('special_price_to', '>=', $now);
                });
        } elseif ($tab === 'upcoming_flash') {
            $query->whereNotNull('special_price')
                ->where('special_price', '>', 0)
                ->whereNotNull('special_price_from')
                ->where('special_price_from', '>', $now);
        } elseif ($tab === 'expired_flash') {
            $query->whereNotNull('special_price')
                ->where('special_price', '>', 0)
                ->whereNotNull('special_price_to')
                ->where('special_price_to', '<', $now);
        } elseif ($tab === 'markdowns') {
            $query->whereNotNull('discount_price')
                ->where('discount_price', '>', 0);
        }

        $products = $query->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        // Transform collection to add computed metrics for presentation
        $products->getCollection()->transform(function (Product $product) use ($now) {
            $hasSpecialPrice = !empty($product->special_price) && $product->special_price > 0;
            $hasDiscountPrice = !empty($product->discount_price) && $product->discount_price > 0;

            $flashStatus = 'none';
            if ($hasSpecialPrice) {
                $fromOk = !$product->special_price_from || $product->special_price_from->lte($now);
                $toOk   = !$product->special_price_to   || $product->special_price_to->gte($now);

                if ($product->special_price_from && $product->special_price_from->gt($now)) {
                    $flashStatus = 'upcoming';
                } elseif ($product->special_price_to && $product->special_price_to->lt($now)) {
                    $flashStatus = 'expired';
                } elseif ($fromOk && $toOk) {
                    $flashStatus = 'active';
                }
            }

            $effectivePrice = $product->effective_price;
            $regularPrice = (float) $product->price;

            $savingsPercent = 0;
            if ($regularPrice > 0 && $effectivePrice < $regularPrice) {
                $savingsPercent = round((($regularPrice - $effectivePrice) / $regularPrice) * 100);
            }

            return [
                'id'                  => $product->id,
                'name'                => $product->name,
                'sku'                 => $product->sku,
                'slug'                => $product->slug,
                'image'               => $product->image,
                'price'               => $regularPrice,
                'cost_price'          => (float) $product->cost_price,
                'discount_price'      => $hasDiscountPrice ? (float) $product->discount_price : null,
                'special_price'       => $hasSpecialPrice ? (float) $product->special_price : null,
                'special_price_from'  => $product->special_price_from ? $product->special_price_from->format('Y-m-d\TH:i') : null,
                'special_price_to'    => $product->special_price_to ? $product->special_price_to->format('Y-m-d\TH:i') : null,
                'special_price_from_formatted' => $product->special_price_from ? $product->special_price_from->format('M d, Y H:i') : null,
                'special_price_to_formatted'   => $product->special_price_to ? $product->special_price_to->format('M d, Y H:i') : null,
                'effective_price'     => $effectivePrice,
                'savings_percent'     => $savingsPercent,
                'is_on_flash_sale'    => $product->is_on_flash_sale,
                'is_on_sale'          => $product->is_on_sale,
                'flash_sale_status'   => $flashStatus,
                'stock'               => (int) $product->stock,
                'inventory_source'    => $product->inventory_source,
                'category'            => $product->category ? [
                    'id'   => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
            ];
        });

        // Calculate KPI summary stats
        $stats = [
            'total_promotions' => Product::where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNotNull('discount_price')->where('discount_price', '>', 0);
                })->orWhere(function ($sub) {
                    $sub->whereNotNull('special_price')->where('special_price', '>', 0);
                });
            })->count(),

            'active_flash' => Product::whereNotNull('special_price')
                ->where('special_price', '>', 0)
                ->where(function ($q) use ($now) {
                    $q->whereNull('special_price_from')->orWhere('special_price_from', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('special_price_to')->orWhere('special_price_to', '>=', $now);
                })->count(),

            'upcoming_flash' => Product::whereNotNull('special_price')
                ->where('special_price', '>', 0)
                ->whereNotNull('special_price_from')
                ->where('special_price_from', '>', $now)->count(),

            'expired_flash' => Product::whereNotNull('special_price')
                ->where('special_price', '>', 0)
                ->whereNotNull('special_price_to')
                ->where('special_price_to', '<', $now)->count(),

            'markdowns' => Product::whereNotNull('discount_price')
                ->where('discount_price', '>', 0)
                ->count(),
        ];

        $categories = Category::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Admin/Discounts/Index', [
            'products'   => $products,
            'categories' => $categories,
            'stats'      => $stats,
            'filters'    => [
                'tab'         => $tab,
                'search'      => $search,
                'category_id' => $categoryId ? (int) $categoryId : '',
            ],
        ]);
    }

    /**
     * Search products for autocomplete picker modal.
     */
    public function searchProducts(Request $request)
    {
        $search = trim($request->input('q', ''));
        if (strlen($search) < 2) {
            return response()->json(['products' => []]);
        }

        $products = Product::select('id', 'name', 'sku', 'price', 'discount_price', 'special_price', 'image', 'stock')
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            })
            ->limit(15)
            ->get();

        return response()->json(['products' => $products]);
    }

    /**
     * Create or apply discounts / flash sales to one product, selected products, or a whole category.
     */
    public function apply(Request $request)
    {
        $validated = $request->validate([
            'target_type'          => ['required', 'in:single,selected,category'],
            'product_id'           => ['required_if:target_type,single', 'nullable', 'exists:products,id'],
            'product_ids'          => ['required_if:target_type,selected', 'nullable', 'array'],
            'product_ids.*'        => ['integer', 'exists:products,id'],
            'category_id'          => ['required_if:target_type,category', 'nullable', 'exists:categories,id'],

            // Pricing actions
            'apply_markdown'       => ['boolean'],
            'markdown_mode'        => ['nullable', 'in:fixed,percent'],
            'markdown_value'       => ['nullable', 'numeric', 'min:0'],

            'apply_flash_sale'     => ['boolean'],
            'flash_mode'           => ['nullable', 'in:fixed,percent'],
            'flash_value'          => ['nullable', 'numeric', 'min:0'],
            'special_price_from'   => ['nullable', 'date'],
            'special_price_to'     => ['nullable', 'date'],
        ]);

        $query = Product::query();

        if ($validated['target_type'] === 'single') {
            $query->where('id', $validated['product_id']);
        } elseif ($validated['target_type'] === 'selected') {
            $query->whereIn('id', $validated['product_ids'] ?? []);
        } elseif ($validated['target_type'] === 'category') {
            $query->where('category_id', $validated['category_id']);
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            return back()->with('error', 'No products matched the selected target.');
        }

        $applyMarkdown = !empty($validated['apply_markdown']);
        $applyFlash    = !empty($validated['apply_flash_sale']);

        $updatedCount = 0;
        foreach ($products as $prod) {
            $basePrice = (float) $prod->price;
            $updates = [];

            if ($applyMarkdown && !empty($validated['markdown_value'])) {
                if ($validated['markdown_mode'] === 'percent') {
                    $percent = min(99.99, (float) $validated['markdown_value']);
                    $updates['discount_price'] = round($basePrice * (1 - ($percent / 100)), 2);
                } else {
                    $updates['discount_price'] = max(0, (float) $validated['markdown_value']);
                }
            }

            if ($applyFlash && !empty($validated['flash_value'])) {
                if ($validated['flash_mode'] === 'percent') {
                    $percent = min(99.99, (float) $validated['flash_value']);
                    $updates['special_price'] = round($basePrice * (1 - ($percent / 100)), 2);
                } else {
                    $updates['special_price'] = max(0, (float) $validated['flash_value']);
                }
                $updates['special_price_from'] = !empty($validated['special_price_from']) ? Carbon::parse($validated['special_price_from']) : null;
                $updates['special_price_to']   = !empty($validated['special_price_to']) ? Carbon::parse($validated['special_price_to']) : null;
            }

            if (!empty($updates)) {
                $prod->update($updates);
                $updatedCount++;
            }
        }

        return back()->with('success', "Discounts successfully updated for {$updatedCount} product(s).");
    }

    /**
     * Update an individual product's discount and flash sale parameters.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'discount_price'     => ['nullable', 'numeric', 'min:0'],
            'special_price'      => ['nullable', 'numeric', 'min:0'],
            'special_price_from' => ['nullable', 'date'],
            'special_price_to'   => ['nullable', 'date'],
        ]);

        $updates = [];
        if (array_key_exists('discount_price', $validated)) {
            $updates['discount_price'] = ($validated['discount_price'] !== null && $validated['discount_price'] > 0)
                ? (float) $validated['discount_price']
                : null;
        }

        if (array_key_exists('special_price', $validated)) {
            $updates['special_price'] = ($validated['special_price'] !== null && $validated['special_price'] > 0)
                ? (float) $validated['special_price']
                : null;
        }

        if (array_key_exists('special_price_from', $validated)) {
            $updates['special_price_from'] = !empty($validated['special_price_from'])
                ? Carbon::parse($validated['special_price_from'])
                : null;
        }

        if (array_key_exists('special_price_to', $validated)) {
            $updates['special_price_to'] = !empty($validated['special_price_to'])
                ? Carbon::parse($validated['special_price_to'])
                : null;
        }

        $product->update($updates);

        return back()->with('success', "Discounts updated for {$product->name}.");
    }

    /**
     * Remove discounts from a product.
     * Scope: 'all' (clears all discounts), 'flash' (clears flash sale only), 'markdown' (clears regular discount only).
     */
    public function destroy(Request $request, Product $product)
    {
        $scope = $request->input('scope', 'all');

        if ($scope === 'flash') {
            $product->update([
                'special_price'      => null,
                'special_price_from' => null,
                'special_price_to'   => null,
            ]);
            $msg = "Flash sale removed from {$product->name}.";
        } elseif ($scope === 'markdown') {
            $product->update([
                'discount_price' => null,
            ]);
            $msg = "Standard discount removed from {$product->name}.";
        } else {
            $product->update([
                'discount_price'     => null,
                'special_price'      => null,
                'special_price_from' => null,
                'special_price_to'   => null,
            ]);
            $msg = "All promotions removed from {$product->name}.";
        }

        return back()->with('success', $msg);
    }

    /**
     * Bulk actions across multiple discounted products.
     */
    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'product_ids'   => ['required', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'action'        => ['required', 'in:clear_all,clear_flash,clear_markdown,extend_flash'],
            'extend_date'   => ['required_if:action,extend_flash', 'nullable', 'date'],
        ]);

        $ids = $validated['product_ids'];
        $count = count($ids);

        if ($validated['action'] === 'clear_all') {
            Product::whereIn('id', $ids)->update([
                'discount_price'     => null,
                'special_price'      => null,
                'special_price_from' => null,
                'special_price_to'   => null,
            ]);
            $message = "Removed all discounts from {$count} product(s).";
        } elseif ($validated['action'] === 'clear_flash') {
            Product::whereIn('id', $ids)->update([
                'special_price'      => null,
                'special_price_from' => null,
                'special_price_to'   => null,
            ]);
            $message = "Cleared flash sales for {$count} product(s).";
        } elseif ($validated['action'] === 'clear_markdown') {
            Product::whereIn('id', $ids)->update([
                'discount_price' => null,
            ]);
            $message = "Cleared standard discounts for {$count} product(s).";
        } elseif ($validated['action'] === 'extend_flash') {
            $extendDate = Carbon::parse($validated['extend_date']);
            Product::whereIn('id', $ids)->update([
                'special_price_to' => $extendDate,
            ]);
            $message = "Extended flash sales end date to {$extendDate->format('M d, Y H:i')} for {$count} product(s).";
        }

        return back()->with('success', $message);
    }
}
