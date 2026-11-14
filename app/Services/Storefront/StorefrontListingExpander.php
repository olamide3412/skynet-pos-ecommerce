<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\ProductPosUnit;
use App\Models\StoreSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class StorefrontListingExpander
{
    /**
     * Check if split selling units is globally enabled.
     */
    public function enabled(): bool
    {
        return StoreSetting::getBool('split_selling_units_in_listing', true);
    }

    /**
     * Get the configured behavior for out-of-stock packaging units.
     * Values: 'show_out_of_stock' | 'hide'
     */
    public function outOfStockBehavior(): string
    {
        return StoreSetting::get('split_unit_out_of_stock_behavior', 'show_out_of_stock');
    }

    /**
     * Eager loading definition for storefront product queries.
     */
    public function eagerLoad(): array
    {
        if (!$this->enabled()) {
            return [];
        }

        return [
            'posUnits' => function ($q) {
                $q->where('unit_kind', 'additional')
                  ->where('is_enabled_online', true)
                  ->where('show_in_listing', true)
                  ->where('synced_standard_price', '>', 0)
                  ->where('quantity_in_base_units', '>', 1)
                  ->orderBy('sort_order')
                  ->orderBy('id');
            }
        ];
    }

    /**
     * Expand a paginator's product collection into base and unit cards.
     */
    public function expandPaginator(LengthAwarePaginator $paginator, ?string $searchTerm = null): LengthAwarePaginator
    {
        if (!$this->enabled()) {
            return $paginator;
        }

        $expandedCollection = $this->expand($paginator->getCollection(), $searchTerm);
        $paginator->setCollection($expandedCollection);

        return $paginator;
    }

    /**
     * Expand a collection and limit total output count (for carousels / widgets).
     */
    public function expandAndLimit(iterable $products, int $limit, ?string $searchTerm = null): Collection
    {
        $collection = $products instanceof Collection ? $products : collect($products);

        if (!$this->enabled()) {
            return $collection->take($limit)->values();
        }

        return $this->expand($collection, $searchTerm)->take($limit)->values();
    }

    /**
     * Main expansion logic: transforms a collection of Product models into
     * an expanded collection containing base product cards and individual selling unit cards.
     */
    public function expand(iterable $products, ?string $searchTerm = null): Collection
    {
        $collection = $products instanceof Collection ? $products : collect($products);

        if (!$this->enabled() || $collection->isEmpty()) {
            return $collection->values();
        }

        $searchNormalized = $searchTerm ? trim(mb_strtolower($searchTerm)) : null;
        $outOfStockRule   = $this->outOfStockBehavior();

        $results = collect();

        foreach ($collection as $product) {
            if (!$product instanceof Product) {
                $results->push($product);
                continue;
            }

            // If product expiry tracking is enabled and product has expired, exclude if configured
            if ($product->isExpired() && StoreSetting::getBool('hide_expired_products_on_storefront', true)) {
                continue;
            }

            // Determine if parent matches the search term directly
            $parentNameNormalized = mb_strtolower($product->name);
            $parentBrandNormalized = mb_strtolower($product->brand?->name ?? '');
            $parentMatched = !$searchNormalized 
                || str_contains($parentNameNormalized, $searchNormalized) 
                || ($parentBrandNormalized && str_contains($parentBrandNormalized, $searchNormalized));

            $listableUnits = $product->posUnits 
                ? $product->posUnits->filter(function ($u) {
                    return $u->unit_kind === 'additional'
                        && $u->is_enabled_online
                        && $u->show_in_listing
                        && (float) $u->synced_standard_price > 0
                        && (float) $u->quantity_in_base_units > 1;
                })
                : collect();

            // 1. Base Product Card
            // If search was specifically for a unit name (and didn't match product name), base card may be excluded
            if ($parentMatched || $listableUnits->isEmpty()) {
                $baseCard = $this->buildBaseProductCard($product);
                $results->push($baseCard);
            }

            // 2. Unit Cards
            if ($listableUnits->isNotEmpty()) {
                foreach ($listableUnits as $unit) {
                    $unitLabel = trim($unit->online_label ?: $unit->unit_name);
                    $unitLabelNormalized = mb_strtolower($unitLabel);

                    // If searching and neither parent nor this unit matched, skip
                    if ($searchNormalized && !$parentMatched && !str_contains($unitLabelNormalized, $searchNormalized)) {
                        continue;
                    }

                    $multiplier = max(1.0, (float) $unit->quantity_in_base_units);
                    
                    // Stock calculation
                    $isPosUnsynced = $product->inventory_source === 'pos' 
                        && in_array($product->pos_sync_status, ['missing_pos_item', 'inactive_pos_item', 'mapping_sync_failed'], true);

                    $unitStock = $isPosUnsynced ? 0 : (int) floor((int) $product->stock / $multiplier);

                    // Out of stock behavior
                    if ($unitStock <= 0 && $outOfStockRule === 'hide') {
                        continue;
                    }

                    $unitCard = $this->buildUnitProductCard($product, $unit, $unitStock, $multiplier, $unitLabel);
                    $results->push($unitCard);
                }
            }
        }

        return $results->values();
    }

    /**
     * Build standard representation for base product card.
     */
    protected function buildBaseProductCard(Product $product): Product
    {
        $product->listing_key     = 'p' . $product->id;
        $product->is_unit_listing = false;
        $product->unit_id         = null;
        $product->unit_label      = null;
        $product->unit_multiplier = 1.0;
        $product->url_query       = [];

        return $product;
    }

    /**
     * Build cloned Product representation for a specific selling unit card.
     */
    protected function buildUnitProductCard(
        Product $parent, 
        ProductPosUnit $unit, 
        int $derivedStock, 
        float $multiplier, 
        string $unitLabel
    ): Product {
        // Clone parent product model so all accessors, category, brand, and relations remain intact
        $card = clone $parent;

        $multStr = rtrim(rtrim(number_format($multiplier, 2, '.', ''), '0'), '.');

        // Formulate friendly title (avoid repeating multiplier if label already has e.g. "x5" or "(24 Units)")
        $hasCountInLabel = preg_match('/(\bx\d+|\b\d+\s*(units|pcs|pieces|bottles|packs|cans|tins)\b)/i', $unitLabel);
        if ($hasCountInLabel) {
            $formattedTitle = "{$parent->name} {$unitLabel}";
        } else {
            $formattedTitle = "{$parent->name} {$unitLabel} x{$multStr}";
        }

        $unitPrice = (float) $unit->synced_standard_price;

        // Custom attributes for the unit card
        $card->listing_key     = "p{$parent->id}-u{$unit->id}";
        $card->is_unit_listing = true;
        $card->unit_id         = $unit->id;
        $card->unit_label      = $unitLabel;
        $card->unit_multiplier = $multiplier;
        $card->url_query       = ['unit' => $unit->id];

        // Override core display attributes
        $card->name            = $formattedTitle;
        $card->price           = $unitPrice;
        $card->effective_price = $unitPrice;
        $card->discount_price  = null;
        $card->special_price   = null;
        $card->is_on_flash_sale= false;
        $card->stock           = $derivedStock;

        // Image fallback: unit custom photo -> parent thumbnail -> empty
        if (!empty($unit->image)) {
            $card->image = $unit->image;
        }

        return $card;
    }
}

