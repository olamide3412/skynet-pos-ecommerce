<?php

namespace App\Services\PosIntegration;

use App\Models\PosConnection;
use App\Models\PosSyncLog;
use App\Models\Product;
use App\Models\ProductPosUnit;
use App\Models\ProductVariantMapping;
use App\Models\StoreSetting;
use App\Models\SyncedPosItem;
use App\Models\SyncedPosItemUnit;
use App\Services\Brands\PosBrandResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PosCatalogService
{
    protected PosApiClient $apiClient;
    protected PosBrandResolver $brandResolver;

    public function __construct(?PosApiClient $apiClient = null, ?PosBrandResolver $brandResolver = null)
    {
        $this->apiClient = $apiClient ?: new PosApiClient();
        $this->brandResolver = $brandResolver ?: new PosBrandResolver();
    }

    /**
     * Synchronize branch brands from POS (gracefully no-ops if POS does not expose /brands yet).
     */
    public function syncBrands(bool $forceFull = false): array
    {
        $conn = PosConnection::active();
        if (!$conn || empty($conn->pos_base_url)) {
            return ['status' => false, 'total_synced' => 0, 'message' => 'No active POS connection.'];
        }

        $since = (!$forceFull && $conn->last_catalog_sync_at)
            ? $conn->last_catalog_sync_at->toIso8601String()
            : null;

        $page = 1;
        $totalSynced = 0;

        try {
            do {
                $response = $this->apiClient->listBrands($since, $page, 100);
                if (!empty($response['unsupported'])) {
                    return [
                        'status' => true,
                        'unsupported' => true,
                        'total_synced' => 0,
                        'message' => 'POS /brands endpoint not available; brands will be resolved from catalog items.',
                    ];
                }

                $brands = $response['data'] ?? [];
                $hasMore = !empty($response['next_page_url']) || (!empty($response['meta']['current_page']) && $response['meta']['current_page'] < $response['meta']['last_page']);

                foreach ($brands as $brandData) {
                    if (is_array($brandData)) {
                        $this->brandResolver->upsertFromPos($brandData);
                        $totalSynced++;
                    }
                }

                $page++;
            } while ($hasMore);

            if ($totalSynced > 0) {
                PosSyncLog::log('brand_sync', 'success', "Synchronized {$totalSynced} brand(s) from POS.");
            }

            return [
                'status' => true,
                'total_synced' => $totalSynced,
                'message' => "Brand synchronization completed: {$totalSynced} brand(s) updated.",
            ];
        } catch (\Throwable $e) {
            Log::warning('POS Brand sync skipped: ' . $e->getMessage());
            return [
                'status' => false,
                'total_synced' => $totalSynced,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Synchronize branch catalog items from POS into local synced_pos_items cache.
     * Can be incremental (using last_catalog_sync_at) or full.
     */
    public function syncCatalog(bool $forceFull = false): array
    {
        @set_time_limit(300);

        $conn = PosConnection::active();
        if (!$conn || empty($conn->pos_base_url)) {
            return ['status' => false, 'message' => 'No active POS connection.'];
        }

        // Sync brands first (if POS exposes /brands) so logos & active flags are ready before items
        $this->syncBrands($forceFull);

        $since = (!$forceFull && $conn->last_catalog_sync_at)
            ? $conn->last_catalog_sync_at->toIso8601String()
            : null;

        $page = 1;
        $totalSynced = 0;
        $activePosIds = [];

        PosSyncLog::log('catalog_sync', 'started', $forceFull ? 'Starting full catalog synchronization' : 'Starting incremental catalog synchronization');

        try {
            do {
                $response = $this->apiClient->listItems($since, $page, 100);
                $items = $response['data'] ?? [];
                $hasMore = !empty($response['next_page_url']) || (!empty($response['meta']['current_page']) && $response['meta']['current_page'] < $response['meta']['last_page']);

                foreach ($items as $itemData) {
                    $this->upsertSyncedItem($itemData);
                    $activePosIds[] = $itemData['id'];
                    $totalSynced++;
                }

                $page++;
            } while ($hasMore);

            // If full sync, check for missing mapped items
            if ($forceFull && !empty($activePosIds)) {
                $this->detectMissingMappedItems($activePosIds);
            }

            $conn->update([
                'last_catalog_sync_at' => now(),
                'last_successful_request_at' => now(),
            ]);

            PosSyncLog::log('catalog_sync', 'success', "Catalog synchronization completed: {$totalSynced} items processed");

            return [
                'status' => true,
                'total_synced' => $totalSynced,
                'message' => "Catalog synchronization completed: {$totalSynced} items updated.",
            ];
        } catch (\Throwable $e) {
            Log::error('POS Catalog sync error: ' . $e->getMessage());
            PosSyncLog::log('catalog_sync', 'failed', 'Catalog synchronization failed: ' . $e->getMessage());
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Upsert a single item and its selling units into synced_pos_items & synced_pos_item_units.
     */
    public function upsertSyncedItem(array $data): SyncedPosItem
    {
        return DB::transaction(function () use ($data) {
            $posBrandId = $data['pos_brand_id'] ?? $data['brand_id'] ?? ($data['brand']['id'] ?? null);
            $posBrandName = $data['pos_brand_name'] ?? $data['brand_name'] ?? ($data['brand']['name'] ?? null);

            if (isset($data['brand']) && is_array($data['brand']) && (!empty($data['brand']['id']) || !empty($data['brand']['name']))) {
                $this->brandResolver->upsertFromPos($data['brand']);
            }

            $syncedItem = SyncedPosItem::updateOrCreate(
                ['pos_item_id' => $data['id']],
                [
                    'pos_item_name' => $data['name'] ?? 'Unnamed POS Item',
                    'pos_category_id' => $data['pos_category_id'] ?? $data['category_id'] ?? $data['category']['id'] ?? null,
                    'pos_category_name' => $data['pos_category_name'] ?? $data['category_name'] ?? $data['category']['name'] ?? null,
                    'pos_brand_id' => $posBrandId,
                    'pos_brand_name' => $posBrandName,
                    'barcode' => $data['barcode'] ?? null,
                    'item_reference' => $data['item_reference'] ?? $data['reference'] ?? null,
                    'sku' => $data['sku'] ?? null,
                    'is_active' => (bool) ($data['is_active'] ?? true),
                    'base_unit_name' => $data['base_unit_name'] ?? $data['unit_name'] ?? 'Piece',
                    'standard_price' => floatval($data['standard_price'] ?? $data['selling_price'] ?? 0),
                    'buying_price' => floatval($data['buying_price'] ?? $data['cost_price'] ?? $data['purchase_price'] ?? $data['cost'] ?? $data['unit_cost'] ?? 0),
                    'effective_availability' => (int) round(floatval($data['effective_availability'] ?? $data['quantity'] ?? 0)),
                    'imei_tracking_enabled' => (bool) ($data['imei_tracking_enabled'] ?? false),
                    'pos_updated_at' => isset($data['updated_at']) ? \Illuminate\Support\Carbon::parse($data['updated_at']) : now(),
                    'last_synced_at' => now(),
                ]
            );

            // Upsert selling units if provided
            if (isset($data['units']) && is_array($data['units'])) {
                // Clear existing synced units to refresh
                $syncedItem->units()->delete();

                // Base unit record
                SyncedPosItemUnit::create([
                    'synced_pos_item_id' => $syncedItem->id,
                    'pos_item_unit_id' => null,
                    'unit_kind' => 'base',
                    'unit_name' => $syncedItem->base_unit_name,
                    'quantity_in_base_units' => 1.0,
                    'standard_price' => $syncedItem->standard_price,
                ]);

                foreach ($data['units'] as $unit) {
                    SyncedPosItemUnit::create([
                        'synced_pos_item_id' => $syncedItem->id,
                        'pos_item_unit_id' => $unit['id'] ?? null,
                        'unit_kind' => 'additional',
                        'unit_name' => $unit['unit_name'] ?? 'Unit',
                        'quantity_in_base_units' => floatval($unit['quantity_in_base_units'] ?? 1.0),
                        'standard_price' => floatval($unit['standard_price'] ?? $unit['selling_price'] ?? 0),
                    ]);
                }
            }

            // If an active ecommerce product is mapped, synchronize current standard price & availability
            $mappedProduct = Product::where('pos_item_id', $syncedItem->pos_item_id)->first();
            if ($mappedProduct) {
                $this->syncProductWithPosData($mappedProduct, $syncedItem);
                $syncedItem->update(['mapping_status' => 'mapped_simple']);
            }

            // If any product variants are mapped to this POS item, synchronize their price & stock as well
            $mappedVariants = ProductVariantMapping::with('product')
                ->where('pos_item_id', $syncedItem->pos_item_id)
                ->get();

            if ($mappedVariants->isNotEmpty()) {
                foreach ($mappedVariants as $variantMapping) {
                    $variantMapping->update([
                        'local_stock' => (int) floor($syncedItem->effective_availability),
                        'local_price' => (float) $syncedItem->standard_price,
                        'last_pos_sync_at' => now(),
                    ]);

                    $parentProduct = $variantMapping->product;
                    if ($parentProduct && is_array($parentProduct->variants)) {
                        $updatedVariants = [];
                        foreach ($parentProduct->variants as $var) {
                            if (($var['pos_item_id'] ?? null) == $syncedItem->pos_item_id || ($var['name'] ?? null) === $variantMapping->variant_label) {
                                $var['stock'] = (int) floor($syncedItem->effective_availability);
                                $var['price'] = (float) $syncedItem->standard_price;
                            }
                            $updatedVariants[] = $var;
                        }
                        $totalVariantStock = !empty($updatedVariants)
                            ? (int) array_sum(array_column($updatedVariants, 'stock'))
                            : (int) ProductVariantMapping::where('product_id', $parentProduct->id)->sum('local_stock');

                        $parentUpdates = [
                            'stock' => $totalVariantStock,
                            'variants' => $updatedVariants,
                        ];

                        $overwriteBrand = StoreSetting::getBool('pos_brand_sync_overwrite', false);
                        if (empty($parentProduct->brand_id) || $overwriteBrand) {
                            $resolvedBrandId = $this->brandResolver->resolve($syncedItem->pos_brand_id, $syncedItem->pos_brand_name);
                            if ($resolvedBrandId !== null) {
                                $parentUpdates['brand_id'] = $resolvedBrandId;
                            }
                        }

                        $parentProduct->updateQuietly($parentUpdates);
                    }
                }

                if (!$mappedProduct) {
                    $syncedItem->update(['mapping_status' => 'mapped_variant']);
                }
            }

            return $syncedItem;
        });
    }

    /**
     * Import a synchronized POS catalog item as an Ecommerce Simple Product.
     */
    public function importAsSimpleProduct(
        int|string $posItemId,
        array $customFields = [],
        bool $registerMapping = true,
        bool $logIndividual = true,
        array &$categoryCache = [],
        array &$brandCache = [],
        ?SyncedPosItem $preloadedSyncedItem = null
    ): Product {
        $syncedItem = $preloadedSyncedItem ?: SyncedPosItem::with('units')->where('pos_item_id', $posItemId)->firstOrFail();

        $status = isset($customFields['status'])
            ? (bool) $customFields['status']
            : (bool) ($customFields['is_active'] ?? false);

        // Check duplicate mapping on simple products
        $existing = Product::where('pos_item_id', $posItemId)->first();
        if ($existing) {
            $updates = [];
            if (isset($customFields['status']) || isset($customFields['is_active'])) {
                $updates['status'] = $status;
            }
            if ($syncedItem->buying_price > 0 || $existing->cost_price === null) {
                $updates['cost_price'] = $syncedItem->buying_price ?? 0;
            }
            if (empty($existing->brand_id)) {
                $resolvedBrandId = $customFields['brand_id']
                    ?? $this->brandResolver->resolve($syncedItem->pos_brand_id, $syncedItem->pos_brand_name, $brandCache);
                if ($resolvedBrandId !== null) {
                    $updates['brand_id'] = $resolvedBrandId;
                }
            }
            if (!empty($updates)) {
                $existing->update($updates);
            }
            return $existing;
        }

        // Check if already consolidated/mapped as a product variant
        $existingVariant = ProductVariantMapping::with('product')
            ->where('pos_item_id', $posItemId)
            ->where('is_active', true)
            ->first();
        if ($existingVariant && $existingVariant->product) {
            return $existingVariant->product;
        }

        $product = DB::transaction(function () use ($syncedItem, $customFields, $status, &$categoryCache, &$brandCache) {
            $name = $customFields['name'] ?? $syncedItem->pos_item_name;
            $slug = Str::slug($name) . '-' . uniqid();
            $urlKey = Str::slug($name) . '-' . Str::random(6);

            // Resolve Category:
            // 1. Explicit override passed in $customFields['category_id']
            // 2. Matching Ecommerce category by pos_category_name (auto-match or auto-create)
            // 3. Fallback to first category
            $categoryId = $customFields['category_id'] ?? null;
            if (!$categoryId && !empty($syncedItem->pos_category_name)) {
                $categoryName = trim($syncedItem->pos_category_name);
                if (isset($categoryCache[$categoryName])) {
                    $categoryId = $categoryCache[$categoryName];
                } else {
                    $matchedCategory = \App\Models\Category::where('name', $categoryName)
                        ->orWhere('slug', Str::slug($categoryName))
                        ->first();

                    if (!$matchedCategory) {
                        $matchedCategory = \App\Models\Category::create([
                            'name' => $categoryName,
                            'slug' => Str::slug($categoryName) ?: 'category-' . uniqid(),
                            'visible_in_menu' => true,
                            'menu_position' => 0,
                        ]);
                    }
                    $categoryId = $matchedCategory->id;
                    $categoryCache[$categoryName] = $categoryId;
                }
            }

            if (!$categoryId) {
                if (isset($categoryCache['__fallback_default'])) {
                    $categoryId = $categoryCache['__fallback_default'];
                } else {
                    $categoryId = \App\Models\Category::first()?->id ?? 1;
                    $categoryCache['__fallback_default'] = $categoryId;
                }
            }

            // Resolve Brand (optional, no fallback):
            // 1. Explicit override passed in $customFields['brand_id']
            // 2. Matching or auto-creating Ecommerce brand via PosBrandResolver
            $brandId = $customFields['brand_id'] ?? null;
            if (!$brandId && (!empty($syncedItem->pos_brand_id) || !empty($syncedItem->pos_brand_name))) {
                $brandId = $this->brandResolver->resolve(
                    $syncedItem->pos_brand_id,
                    $syncedItem->pos_brand_name,
                    $brandCache
                );
            }

            $product = Product::create([
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'name' => $name,
                'slug' => $slug,
                'url_key' => $urlKey,
                'sku' => $syncedItem->sku,
                'description' => $customFields['description'] ?? "<p>{$syncedItem->pos_item_name}</p>",
                'short_description' => $customFields['short_description'] ?? $syncedItem->pos_item_name,
                'price' => $syncedItem->standard_price,
                'cost_price' => $customFields['cost_price'] ?? $syncedItem->buying_price ?? 0,
                'stock' => (int) floor($syncedItem->effective_availability),
                'status' => $status, // Active or draft based on choice
                'is_new' => true,
                'is_featured' => false,
                'visible_individually' => true,
                'show_stock_level' => true,
                'inventory_source' => 'pos',
                'pos_item_id' => $syncedItem->pos_item_id,
                'pos_item_name_snapshot' => $syncedItem->pos_item_name,
                'pos_base_unit_name' => $syncedItem->base_unit_name,
                'pos_sync_status' => 'synced',
                'pos_item_updated_at' => $syncedItem->pos_updated_at,
                'last_pos_sync_at' => now(),
            ]);

            // Synchronize POS selling units automatically into product_pos_units
            $this->syncProductUnits($product, $syncedItem);

            // Update mapping status on synced item
            $syncedItem->update(['mapping_status' => 'mapped_simple']);

            return $product;
        });

        // Perform external HTTP registration and audit logging AFTER transaction commits
        if ($registerMapping) {
            $this->apiClient->registerMappings([
                [
                    'pos_item_id' => $syncedItem->pos_item_id,
                    'ecommerce_product_id' => $product->id,
                    'type' => 'simple',
                ],
            ]);
        }

        if ($logIndividual) {
            $statusName = $status ? 'active' : 'draft';
            PosSyncLog::log('item_import', 'success', "Imported POS item #{$syncedItem->pos_item_id} as {$statusName} product: {$product->name}");
        }

        return $product;
    }

    /**
     * Bulk import multiple synchronized POS items as simple products.
     * High-performance chunked processing with batched API mapping registration.
     */
    public function bulkImportSimpleProducts(array $posItemIds, array $customFields = []): array
    {
        @set_time_limit(300);

        $imported = [];
        $errors = [];
        $mappingsToRegister = [];
        $categoryCache = [];
        $brandCache = [];

        // Process in chunks of 200 items to keep memory and query overhead low
        foreach (array_chunk($posItemIds, 200) as $chunkIds) {
            $syncedItems = SyncedPosItem::with('units')
                ->whereIn('pos_item_id', $chunkIds)
                ->get()
                ->keyBy('pos_item_id');

            foreach ($chunkIds as $posItemId) {
                try {
                    $syncedItem = $syncedItems->get($posItemId);
                    if (!$syncedItem) {
                        continue;
                    }

                    $product = $this->importAsSimpleProduct(
                        posItemId: $posItemId,
                        customFields: $customFields,
                        registerMapping: false,
                        logIndividual: false,
                        categoryCache: $categoryCache,
                        brandCache: $brandCache,
                        preloadedSyncedItem: $syncedItem
                    );

                    $imported[] = $product;
                    $mappingsToRegister[] = [
                        'pos_item_id' => (int) $posItemId,
                        'ecommerce_product_id' => $product->id,
                        'type' => 'simple',
                    ];
                } catch (\Throwable $e) {
                    $errors[] = [
                        'pos_item_id' => $posItemId,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        // Register all mappings with POS deletion protection in batches of 250
        if (!empty($mappingsToRegister)) {
            foreach (array_chunk($mappingsToRegister, 250) as $chunk) {
                try {
                    $this->apiClient->registerMappings($chunk);
                } catch (\Throwable $e) {
                    Log::warning("Failed to register bulk mappings chunk with POS: " . $e->getMessage());
                }
            }
        }

        $total = count($imported);
        $statusLabel = (!empty($customFields['status']) || !empty($customFields['is_active'])) ? 'active' : 'draft';
        PosSyncLog::log('item_import', 'success', "Bulk imported {$total} POS items as {$statusLabel} products.");

        return [
            'status' => true,
            'total_imported' => $total,
            'errors' => $errors,
        ];
    }

    /**
     * Import all currently unmapped eligible items.
     */
    public function importAllEligible(array $customFields = []): array
    {
        @set_time_limit(300);

        $unmappedIds = SyncedPosItem::where('mapping_status', 'unmapped')
            ->where('is_active', true)
            ->pluck('pos_item_id')
            ->toArray();

        return $this->bulkImportSimpleProducts($unmappedIds, $customFields);
    }

    /**
     * Synchronize product_pos_units for a mapped product based on synced_pos_items.
     */
    public function syncProductUnits(Product $product, SyncedPosItem $syncedItem): void
    {
        // Keep existing enabled flags if already configured
        $existingUnits = $product->posUnits->keyBy(fn($u) => $u->unit_kind . '_' . $u->pos_item_unit_id);

        $syncedUnits = $syncedItem->units()->get();

        // Always ensure base unit is present
        $baseExisting = $existingUnits->get('base_');
        ProductPosUnit::updateOrCreate(
            [
                'product_id' => $product->id,
                'pos_item_unit_id' => null,
                'unit_kind' => 'base',
            ],
            [
                'unit_name' => $syncedItem->base_unit_name,
                'quantity_in_base_units' => 1.0,
                'synced_standard_price' => $syncedItem->standard_price,
                'online_label' => $baseExisting?->online_label ?? $syncedItem->base_unit_name,
                'is_enabled_online' => true,
                'sort_order' => 0,
                'last_pos_sync_at' => now(),
            ]
        );

        // Additional units
        foreach ($syncedUnits->where('unit_kind', 'additional') as $index => $unit) {
            $key = 'additional_' . $unit->pos_item_unit_id;
            $exist = $existingUnits->get($key);

            ProductPosUnit::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'pos_item_unit_id' => $unit->pos_item_unit_id,
                    'unit_kind' => 'additional',
                ],
                [
                    'unit_name' => $unit->unit_name,
                    'quantity_in_base_units' => $unit->quantity_in_base_units,
                    'synced_standard_price' => $unit->standard_price,
                    'online_label' => $exist?->online_label ?? $unit->unit_name,
                    'is_enabled_online' => $exist ? $exist->is_enabled_online : true,
                    'sort_order' => $index + 1,
                    'last_pos_sync_at' => now(),
                ]
            );
        }
    }

    /**
     * Synchronize a product with updated POS price and stock data.
     */
    protected function syncProductWithPosData(Product $product, SyncedPosItem $syncedItem): void
    {
        $updateData = [
            'price' => $syncedItem->standard_price,
            'pos_item_name_snapshot' => $syncedItem->pos_item_name,
            'pos_base_unit_name' => $syncedItem->base_unit_name,
            'pos_sync_status' => $syncedItem->is_active ? 'synced' : 'inactive_pos_item',
            'last_pos_sync_at' => now(),
        ];

        if (empty($product->variants)) {
            $updateData['stock'] = (int) floor($syncedItem->effective_availability);
        }

        if ($syncedItem->buying_price > 0 || $product->cost_price === null) {
            $updateData['cost_price'] = $syncedItem->buying_price ?? 0;
        }

        // Apply brand overwrite policy: fill empty brand_id by default, or overwrite if pos_brand_sync_overwrite is enabled
        $overwriteBrand = StoreSetting::getBool('pos_brand_sync_overwrite', false);
        if (empty($product->brand_id) || $overwriteBrand) {
            $resolvedBrandId = $this->brandResolver->resolve($syncedItem->pos_brand_id, $syncedItem->pos_brand_name);
            if ($resolvedBrandId !== null) {
                $updateData['brand_id'] = $resolvedBrandId;
            }
        }

        $product->update($updateData);

        $this->syncProductUnits($product, $syncedItem);
    }

    /**
     * Detect mapped products whose POS item no longer exists in POS.
     * Follows Section 42A-G: Never delete, keep previous pos_item_id, mark missing_pos_item, disable purchase.
     */
    protected function detectMissingMappedItems(array $activePosIds): void
    {
        $mappedProducts = Product::where('inventory_source', 'pos')
            ->whereNotNull('pos_item_id')
            ->whereNotIn('pos_item_id', $activePosIds)
            ->get();

        foreach ($mappedProducts as $prod) {
            $prod->update([
                'pos_sync_status' => 'missing_pos_item',
                'stock' => 0, // override cached stock to prevent checkout
                'last_pos_sync_at' => now(),
            ]);

            PosSyncLog::log('missing_item_detected', 'warning', "POS Item #{$prod->pos_item_id} mapped to Product #{$prod->id} ({$prod->name}) is missing from POS branch catalog.", (string) $prod->id);
        }
    }

    /**
     * Reconcile all currently mapped products with the POS deletion-protection registry.
     */
    public function reconcileAllMappings(): array
    {
        @set_time_limit(300);

        // 1. Simple product mappings
        $mappedProducts = Product::where('inventory_source', 'pos')
            ->whereNotNull('pos_item_id')
            ->get(['id', 'pos_item_id']);

        $simplePosItemIds = $mappedProducts->pluck('pos_item_id')->map(fn($id) => (int) $id)->all();

        // 2. Variant product mappings
        $mappedVariants = ProductVariantMapping::where('inventory_source', 'pos')
            ->whereNotNull('pos_item_id')
            ->where('is_active', true)
            ->get(['id', 'product_id', 'pos_item_id', 'variant_key']);

        $variantPosItemIds = $mappedVariants->pluck('pos_item_id')->map(fn($id) => (int) $id)->all();

        $allActivePosItemIds = array_values(array_unique(array_merge($simplePosItemIds, $variantPosItemIds)));

        // 3. Ensure local synced_pos_items mapping_status matches active products & variants
        SyncedPosItem::where('mapping_status', '!=', 'unmapped')
            ->when(!empty($allActivePosItemIds), fn($q) => $q->whereNotIn('pos_item_id', $allActivePosItemIds))
            ->update(['mapping_status' => 'unmapped']);

        if (!empty($simplePosItemIds)) {
            SyncedPosItem::whereIn('pos_item_id', $simplePosItemIds)
                ->update(['mapping_status' => 'mapped_simple']);
        }

        if (!empty($variantPosItemIds)) {
            SyncedPosItem::whereIn('pos_item_id', $variantPosItemIds)
                ->whereNotIn('pos_item_id', $simplePosItemIds)
                ->update(['mapping_status' => 'mapped_variant']);
        }

        // 4. Build deletion protection mappings payload
        $mappings = [];
        foreach ($mappedProducts as $product) {
            $mappings[] = [
                'pos_item_id' => (int) $product->pos_item_id,
                'ecommerce_product_id' => (string) $product->id,
                'type' => 'simple',
            ];
        }

        foreach ($mappedVariants as $variant) {
            $mappings[] = [
                'pos_item_id' => (int) $variant->pos_item_id,
                'ecommerce_product_id' => (string) $variant->product_id,
                'type' => 'variant',
                'variant_key' => $variant->variant_key,
            ];
        }

        return $this->apiClient->reconcileMappings($mappings);
    }
}
