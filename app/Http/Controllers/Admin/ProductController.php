<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPosUnit;
use App\Models\ProductVariantMapping;
use App\Models\SyncedPosItem;
use App\Services\ActivityLogger;
use App\Services\ImageOptimizer;
use App\Services\PosIntegration\PosApiClient;
use App\Services\PosIntegration\PosCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProductController extends Controller
{
    protected PosCatalogService $catalogService;
    protected PosApiClient $apiClient;
    protected ImageOptimizer $imageOptimizer;

    public function __construct(
        PosCatalogService $catalogService,
        PosApiClient $apiClient,
        ImageOptimizer $imageOptimizer
    ) {
        $this->catalogService = $catalogService;
        $this->apiClient = $apiClient;
        $this->imageOptimizer = $imageOptimizer;
    }

    public function index(Request $request)
    {
        $query = Product::with(['category', 'posUnits', 'syncedPosItem', 'variantMappings.syncedPosItem'])->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('url_key', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status === 'active' ? 1 : 0);
        }

        if ($invSource = $request->input('inventory_source')) {
            $query->where('inventory_source', $invSource);
        }

        // List synced POS items for selection in the product creation modal
        $syncedItems = SyncedPosItem::where('is_active', true)
            ->select('pos_item_id', 'pos_item_name', 'pos_category_name', 'sku', 'barcode', 'item_reference', 'standard_price', 'buying_price', 'effective_availability', 'base_unit_name', 'mapping_status')
            ->orderBy('pos_item_name')
            ->get();

        $mappedProductsByPosId = Product::whereNotNull('pos_item_id')
            ->select('id', 'name', 'pos_item_id', 'status', 'visible_individually')
            ->get()
            ->keyBy('pos_item_id');

        $mappedVariantsByPosId = ProductVariantMapping::with('product:id,name')
            ->whereNotNull('pos_item_id')
            ->where('is_active', true)
            ->get()
            ->keyBy('pos_item_id');

        $syncedItems->transform(function ($item) use ($mappedProductsByPosId, $mappedVariantsByPosId) {
            $prod = $mappedProductsByPosId->get($item->pos_item_id);
            $var = $mappedVariantsByPosId->get($item->pos_item_id);
            $item->mapped_product = $prod ? [
                'id'                   => $prod->id,
                'name'                 => $prod->name,
                'status'               => (bool) $prod->status,
                'visible_individually' => (bool) $prod->visible_individually,
            ] : null;
            $item->mapped_variant = $var ? [
                'id'            => $var->id,
                'product_id'    => $var->product_id,
                'product_name'  => $var->product?->name ?? ('Product #' . $var->product_id),
                'variant_label' => $var->variant_label,
            ] : null;
            return $item;
        });

        $perPageInput = $request->input('per_page', 25);
        if ($perPageInput === 'all' || $perPageInput === 'ALL') {
            $totalCount = (clone $query)->count();
            $perPage = max(1, $totalCount);
        } else {
            $perPage = in_array((int)$perPageInput, [25, 50, 100], true) ? (int)$perPageInput : 25;
        }

        return Inertia::render('Admin/Products/Index', [
            'products' => $query->paginate($perPage)->withQueryString(),
            'categories' => Category::whereNull('parent_id')
                ->with(['children' => fn($q) => $q->orderBy('name')])
                ->with('attributes')
                ->orderBy('name')
                ->get(),
            'synced_pos_items' => $syncedItems,
            'store_settings' => \App\Models\StoreSetting::allAsArray(),
            'filters' => array_merge($request->only(['search', 'category_id', 'status', 'inventory_source']), [
                'per_page' => (string) $perPageInput,
            ]),
        ]);
    }

    /** AJAX: search products for the related-products picker */
    public function searchRelated(Request $request)
    {
        $q = trim($request->get('q', ''));
        $exclude = (int) $request->get('exclude', 0);

        $query = Product::orderBy('name')->select('id', 'name', 'image', 'url_key');
        if ($q)
            $query->where('name', 'like', '%' . $q . '%');
        if ($exclude)
            $query->where('id', '!=', $exclude);

        return response()->json($query->limit(15)->get());
    }

    /** Return minimal product data for pre-selected IDs (used when editing) */
    public function relatedByIds(Request $request)
    {
        $ids = array_filter(explode(',', $request->get('ids', '')), 'is_numeric');
        return response()->json(
            Product::whereIn('id', $ids)->select('id', 'name', 'image', 'url_key')->get()
        );
    }

    private function rules(bool $isUpdate = false, ?Product $product = null): array
    {
        $uniqueSuffix = $isUpdate ? ",{$product->id}" : '';
        return [
            'inventory_source'     => 'required|in:pos,ecommerce',
            'pos_item_id'          => "nullable|required_if:inventory_source,pos|integer|unique:products,pos_item_id{$uniqueSuffix}",
            'name'                 => 'required|string|max:255',
            'category_id'          => 'required|exists:categories,id',
            'short_description'    => 'nullable|string',
            'description'          => 'nullable|string',
            'meta_title'           => 'nullable|string|max:255',
            'meta_description'     => 'nullable|string|max:1000',
            'meta_keywords'        => 'nullable|string|max:1000',
            'url_key'              => "required|string|max:255|unique:products,url_key{$uniqueSuffix}",
            'product_number'       => "nullable|string|max:100|unique:products,product_number{$uniqueSuffix}",
            'sku'                  => "nullable|string|max:100|unique:products,sku{$uniqueSuffix}",
            'price'                => 'required|numeric|min:0',
            'cost_price'           => 'nullable|numeric|min:0',
            'discount_price'       => 'nullable|numeric|min:0',
            'special_price'        => 'nullable|numeric|min:0',
            'special_price_from'   => 'nullable|date',
            'special_price_to'     => 'nullable|date|after_or_equal:special_price_from',
            'stock'                => 'required|integer|min:0',
            'is_new'               => 'boolean',
            'is_featured'          => 'boolean',
            'visible_individually' => 'boolean',
            'status'               => 'boolean',
            'show_stock_level'     => 'boolean',
            'related_products'     => 'nullable|array',
            'related_products.*'   => 'exists:products,id',
            'variants'             => 'nullable|string',
            'attributes'           => 'nullable|string',
            'image'                => 'nullable|image|max:10240',
            'images'               => 'nullable|array|max:10',
            'images.*'             => 'image|max:10240',
            'compress_images'      => 'nullable',
        ];
    }

    private function processRequest(Request $request, array $validated): array
    {
        $validated['slug'] = Str::slug($validated['name']) . '-' . uniqid();

        // Booleans sent as strings from FormData
        foreach (['is_new', 'is_featured', 'visible_individually', 'status', 'show_stock_level'] as $bool) {
            $validated[$bool] = filter_var($validated[$bool] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        // If POS linked, load values from SyncedPosItem
        if (($validated['inventory_source'] ?? '') === 'pos' && !empty($validated['pos_item_id'])) {
            $syncedItem = SyncedPosItem::where('pos_item_id', $validated['pos_item_id'])->first();
            if ($syncedItem) {
                $validated['price'] = $syncedItem->standard_price;
                $validated['stock'] = (int) floor($syncedItem->effective_availability);
                $validated['pos_item_name_snapshot'] = $syncedItem->pos_item_name;
                $validated['pos_base_unit_name'] = $syncedItem->base_unit_name;
                $validated['pos_sync_status'] = 'synced';
                $validated['last_pos_sync_at'] = now();
            }
        } else {
            $validated['inventory_source'] = 'ecommerce';
            $validated['pos_item_id'] = null;
            $validated['pos_sync_status'] = 'synced';
        }

        // related_products comes as a JSON string from FormData
        if (isset($validated['related_products']) && is_string($validated['related_products'])) {
            $validated['related_products'] = json_decode($validated['related_products'], true) ?: [];
        }

        if ($request->has('variants') && $request->input('variants')) {
            $validated['variants'] = json_decode($request->input('variants'), true);
        }

        if ($request->has('attributes') && $request->input('attributes')) {
            $validated['attributes'] = json_decode($request->input('attributes'), true);
        }

        $shouldCompress = filter_var($request->input('compress_images', true), FILTER_VALIDATE_BOOLEAN);
        unset($validated['compress_images']);

        $baseSlug = !empty($validated['url_key'])
            ? Str::slug($validated['url_key'])
            : Str::slug($validated['name'] ?? 'product');

        if ($request->hasFile('image')) {
            $validated['image'] = $this->imageOptimizer->store(
                file: $request->file('image'),
                directory: 'products',
                compress: $shouldCompress,
                slug: $baseSlug
            );
        } else {
            unset($validated['image']);
        }

        if ($request->hasFile('images')) {
            $paths = [];
            foreach ($request->file('images') as $index => $file) {
                $gallerySlug = "{$baseSlug}-gallery-" . ($index + 1);
                $paths[] = $this->imageOptimizer->store(
                    file: $file,
                    directory: 'products',
                    compress: $shouldCompress,
                    slug: $gallerySlug
                );
            }
            $validated['images'] = $paths;
        } else {
            unset($validated['images']);
        }

        return $validated;
    }

    public function store(Request $request)
    {
        // Decode related_products JSON string before validation (FormData sends strings)
        if ($request->has('related_products') && is_string($request->related_products)) {
            $request->merge(['related_products' => json_decode($request->related_products, true) ?: []]);
        }

        $validated = $request->validate($this->rules());
        $validated = $this->processRequest($request, $validated);

        $product = Product::create($validated);

        // Synchronize variants & POS variant mappings
        $variants = is_array($validated['variants'] ?? null) ? $validated['variants'] : [];
        $this->syncProductVariantMappings($product, $variants);

        // If POS linked, synchronize units and update mapping status
        if ($product->isPosLinked()) {
            $syncedItem = SyncedPosItem::where('pos_item_id', $product->pos_item_id)->first();
            if ($syncedItem) {
                $this->catalogService->syncProductUnits($product, $syncedItem);
                $syncedItem->update(['mapping_status' => 'mapped_simple']);

                // Register mapping with POS
                $this->apiClient->registerMappings([
                    [
                        'pos_item_id'          => $product->pos_item_id,
                        'ecommerce_product_id' => $product->id,
                        'type'                 => 'simple',
                    ]
                ]);
            }
        }

        ActivityLogger::product("Created product '{$product->name}' (ID: #{$product->id})", [
            'product_id'       => $product->id,
            'price'            => $product->price,
            'stock'            => $product->stock,
            'inventory_source' => $product->inventory_source,
        ]);

        return back()->with('success', 'Product created successfully.');
    }

    public function update(Request $request, Product $product)
    {
        // Decode related_products JSON string before validation (FormData sends strings)
        if ($request->has('related_products') && is_string($request->related_products)) {
            $request->merge(['related_products' => json_decode($request->related_products, true) ?: []]);
        }

        $oldPosItemId = $product->pos_item_id;

        $validated = $request->validate($this->rules(true, $product));
        $validated = $this->processRequest($request, $validated);

        $product->update($validated);

        // Synchronize variants & POS variant mappings
        $variants = is_array($validated['variants'] ?? null) ? $validated['variants'] : [];
        $this->syncProductVariantMappings($product, $variants);

        // Handle POS mapping transition
        if ($product->isPosLinked()) {
            $syncedItem = SyncedPosItem::where('pos_item_id', $product->pos_item_id)->first();
            if ($syncedItem) {
                $this->catalogService->syncProductUnits($product, $syncedItem);
                $syncedItem->update(['mapping_status' => 'mapped_simple']);

                $this->apiClient->registerMappings([
                    [
                        'pos_item_id'          => $product->pos_item_id,
                        'ecommerce_product_id' => $product->id,
                        'type'                 => 'simple',
                    ]
                ]);
            }
        } elseif ($oldPosItemId) {
            // Unmapped or converted to Ecommerce Only
            SyncedPosItem::where('pos_item_id', $oldPosItemId)->update(['mapping_status' => 'unmapped']);
            $this->apiClient->unregisterMappings([$oldPosItemId]);
            try {
                $this->catalogService->reconcileAllMappings();
            } catch (\Throwable $e) {
                // Non-blocking reconciliation
            }
        }

        ActivityLogger::product("Updated product '{$product->name}' (ID: #{$product->id})", [
            'product_id' => $product->id,
            'price'      => $product->price,
            'stock'      => $product->stock,
        ]);

        return back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $posItemId = $product->pos_item_id;
        $name = $product->name;
        $id = $product->id;
        $variantPosItemIds = $product->variantMappings()->pluck('pos_item_id')->filter()->all();

        $product->delete();

        if ($posItemId) {
            SyncedPosItem::where('pos_item_id', $posItemId)->update(['mapping_status' => 'unmapped']);
            $this->apiClient->unregisterMappings([$posItemId]);
        }

        if (!empty($variantPosItemIds)) {
            foreach ($variantPosItemIds as $vPosId) {
                $stillUsed = ProductVariantMapping::where('pos_item_id', $vPosId)->exists()
                    || Product::where('pos_item_id', $vPosId)->exists();
                if (!$stillUsed) {
                    SyncedPosItem::where('pos_item_id', $vPosId)->update(['mapping_status' => 'unmapped']);
                }
            }
            $this->apiClient->unregisterMappings($variantPosItemIds);
        }

        try {
            $this->catalogService->reconcileAllMappings();
        } catch (\Throwable $e) {
            // Non-blocking reconciliation
        }

        ActivityLogger::product("Deleted product '{$name}' (ID: #{$id})", [
            'product_id' => $id,
        ]);

        return back()->with('success', 'Product deleted.');
    }

    /**
     * Synchronize product variant mappings with POS deletion protection and SyncedPosItem mapping statuses.
     */
    protected function syncProductVariantMappings(Product $product, array $variants): void
    {
        $existingMappings = $product->variantMappings()->get()->keyBy('id');
        $retainedMappingIds = [];
        $newlyMappedPosItemIds = [];
        $previouslyMappedPosItemIds = $existingMappings->pluck('pos_item_id')->filter()->unique()->all();
        $updatedVariantsJson = [];

        foreach ($variants as $index => $varData) {
            $name = trim($varData['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $invSource = ($varData['inventory_source'] ?? '') === 'pos' ? 'pos' : 'ecommerce';
            $posItemId = ($invSource === 'pos' && !empty($varData['pos_item_id'])) ? (int) $varData['pos_item_id'] : null;

            $localStock = isset($varData['stock']) && is_numeric($varData['stock']) ? (int) $varData['stock'] : 0;
            $localPrice = isset($varData['price']) && is_numeric($varData['price']) ? (float) $varData['price'] : null;

            if ($posItemId) {
                $syncedItem = SyncedPosItem::where('pos_item_id', $posItemId)->first();
                if ($syncedItem) {
                    if ($localPrice === null || $localPrice <= 0) {
                        $localPrice = (float) $syncedItem->standard_price;
                    }
                    $localStock = (int) floor($syncedItem->effective_availability);
                }

                // If this POS item was previously a standalone POS product on the storefront,
                // consolidate/absorb it (unless admin explicitly chose to keep both active)
                $absorbStandalone = filter_var($varData['absorb_standalone'] ?? true, FILTER_VALIDATE_BOOLEAN);
                if ($absorbStandalone) {
                    $standaloneProduct = Product::where('pos_item_id', $posItemId)
                        ->where('id', '!=', $product->id)
                        ->first();

                    if ($standaloneProduct) {
                        CartItem::where('product_id', $standaloneProduct->id)->delete();
                        $hasOrders = OrderItem::where('product_id', $standaloneProduct->id)->exists();

                        if (!$hasOrders) {
                            $standaloneProduct->posUnits()->delete();
                            $standaloneProduct->variantMappings()->delete();
                            $standaloneProduct->delete();
                        } else {
                            // Preserve order history while removing duplicate storefront listing & freeing POS item
                            $standaloneProduct->updateQuietly([
                                'status'               => false,
                                'visible_individually' => false,
                                'pos_item_id'          => null,
                                'sku'                  => null,
                            ]);
                        }

                        ActivityLogger::product("Consolidated standalone POS product '{$standaloneProduct->name}' (#{$standaloneProduct->id}) into variant '{$name}' of '{$product->name}' (#{$product->id})", [
                            'parent_product_id'   => $product->id,
                            'absorbed_product_id' => $standaloneProduct->id,
                            'pos_item_id'         => $posItemId,
                        ]);
                    }
                }
            }

            $variantKey = !empty($varData['key']) ? $varData['key'] : Str::slug($name, '_');

            // Find matching mapping by id or key/label
            $mappingId = $varData['id'] ?? null;
            $mapping = null;
            if ($mappingId && $existingMappings->has($mappingId)) {
                $mapping = $existingMappings->get($mappingId);
            } else {
                $mapping = $existingMappings->firstWhere('variant_key', $variantKey)
                    ?? $existingMappings->firstWhere('variant_label', $name);
            }

            $payload = [
                'variant_key'      => $variantKey,
                'variant_label'    => $name,
                'pos_item_id'      => $posItemId,
                'inventory_source' => $invSource,
                'local_stock'      => $localStock,
                'local_price'      => $localPrice,
                'is_active'        => true,
                'sort_order'       => $index,
                'last_pos_sync_at' => $posItemId ? now() : null,
            ];

            if ($mapping) {
                $mapping->update($payload);
            } else {
                $mapping = $product->variantMappings()->create($payload);
            }

            $retainedMappingIds[] = $mapping->id;

            if ($posItemId) {
                $newlyMappedPosItemIds[] = $posItemId;
            }

            $updatedVariantsJson[] = [
                'id'               => $mapping->id,
                'name'             => $name,
                'price'            => $localPrice,
                'stock'            => $localStock,
                'inventory_source' => $invSource,
                'pos_item_id'      => $posItemId,
            ];
        }

        // Delete removed mappings
        $deletedMappings = $existingMappings->whereNotIn('id', $retainedMappingIds);
        foreach ($deletedMappings as $del) {
            $del->delete();
        }

        // Keep product variants JSON column and total parent stock in sync
        if (!empty($updatedVariantsJson)) {
            $totalVariantStock = (int) array_sum(array_column($updatedVariantsJson, 'stock'));
        } elseif ($product->inventory_source === 'pos' && $product->pos_item_id) {
            $baseSynced = SyncedPosItem::where('pos_item_id', $product->pos_item_id)->first();
            $totalVariantStock = $baseSynced ? (int) floor($baseSynced->effective_availability) : $product->stock;
        } else {
            $totalVariantStock = $product->stock;
        }

        $product->updateQuietly([
            'stock'    => $totalVariantStock,
            'variants' => $updatedVariantsJson,
        ]);

        // Update SyncedPosItem mapping_status
        if (!empty($newlyMappedPosItemIds)) {
            $activeSimplePosIds = Product::whereIn('pos_item_id', $newlyMappedPosItemIds)
                ->pluck('pos_item_id')
                ->all();

            SyncedPosItem::whereIn('pos_item_id', $newlyMappedPosItemIds)
                ->when(!empty($activeSimplePosIds), fn ($q) => $q->whereNotIn('pos_item_id', $activeSimplePosIds))
                ->update(['mapping_status' => 'mapped_variant']);

            $posMappingsToRegister = [];
            foreach ($product->variantMappings()->whereNotNull('pos_item_id')->get() as $vm) {
                $posMappingsToRegister[] = [
                    'pos_item_id'          => (int) $vm->pos_item_id,
                    'ecommerce_product_id' => (string) $product->id,
                    'type'                 => 'variant',
                    'variant_key'          => $vm->variant_key,
                ];
            }
            if (!empty($posMappingsToRegister)) {
                $this->apiClient->registerMappings($posMappingsToRegister);
            }
        }

        // Unmap items that were removed
        $unmappedPosItemIds = array_diff($previouslyMappedPosItemIds, $newlyMappedPosItemIds);
        if (!empty($unmappedPosItemIds)) {
            foreach ($unmappedPosItemIds as $unmappedId) {
                $stillUsed = ProductVariantMapping::where('pos_item_id', $unmappedId)->exists()
                    || Product::where('pos_item_id', $unmappedId)->exists();
                if (!$stillUsed) {
                    SyncedPosItem::where('pos_item_id', $unmappedId)->update(['mapping_status' => 'unmapped']);
                }
            }
            $this->apiClient->unregisterMappings($unmappedPosItemIds);
        }
    }

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'ids'             => 'required|array|min:1',
            'ids.*'           => 'integer|exists:products,id',
            'action'          => 'required|string|in:update_category,update_status,update_featured,update_new,update_prices,delete',
            'category_id'     => 'nullable|required_if:action,update_category|exists:categories,id',
            'status'          => 'nullable|boolean',
            'is_featured'     => 'nullable|boolean',
            'is_new'          => 'nullable|boolean',
            'price_type'      => 'nullable|in:percentage,fixed',
            'price_operation' => 'nullable|in:increase,decrease',
            'price_amount'    => 'nullable|numeric|min:0',
        ]);

        $ids = $validated['ids'];
        $count = count($ids);

        switch ($validated['action']) {
            case 'update_category':
                Product::whereIn('id', $ids)->update(['category_id' => $validated['category_id']]);
                $category = Category::find($validated['category_id']);
                ActivityLogger::product("Bulk moved {$count} product(s) to category '{$category?->name}'", ['ids' => $ids]);
                return back()->with('success', "Moved {$count} product(s) to {$category->name}.");

            case 'update_status':
                $status = filter_var($request->input('status', true), FILTER_VALIDATE_BOOLEAN);
                Product::whereIn('id', $ids)->update(['status' => $status]);
                $label = $status ? 'Active' : 'Inactive';
                ActivityLogger::product("Bulk changed status of {$count} product(s) to {$label}", ['ids' => $ids, 'status' => $status]);
                return back()->with('success', "Marked {$count} product(s) as {$label}.");

            case 'update_featured':
                $featured = filter_var($request->input('is_featured', true), FILTER_VALIDATE_BOOLEAN);
                Product::whereIn('id', $ids)->update(['is_featured' => $featured]);
                $label = $featured ? 'Featured' : 'Standard';
                ActivityLogger::product("Bulk updated featured flag of {$count} product(s) to {$label}", ['ids' => $ids]);
                return back()->with('success', "Updated featured status for {$count} product(s).");

            case 'update_new':
                $isNew = filter_var($request->input('is_new', true), FILTER_VALIDATE_BOOLEAN);
                Product::whereIn('id', $ids)->update(['is_new' => $isNew]);
                $label = $isNew ? 'New' : 'Standard';
                ActivityLogger::product("Bulk updated New badge of {$count} product(s) to {$label}", ['ids' => $ids]);
                return back()->with('success', "Updated New badge for {$count} product(s).");

            case 'update_prices':
                $op = $request->input('price_operation', 'increase');
                $type = $request->input('price_type', 'percentage');
                $amt = (float) $request->input('price_amount', 0);
                if ($amt > 0) {
                    $products = Product::whereIn('id', $ids)->get();
                    foreach ($products as $p) {
                        $current = (float) $p->price;
                        $delta = $type === 'percentage' ? ($current * ($amt / 100)) : $amt;
                        $newPrice = $op === 'increase' ? ($current + $delta) : max(0, $current - $delta);
                        $p->update(['price' => round($newPrice, 2)]);
                    }
                    ActivityLogger::product("Bulk updated prices for {$count} product(s) ({$op} {$amt}" . ($type === 'percentage' ? '%' : '₦') . ")", ['ids' => $ids]);
                    return back()->with('success', "Updated prices for {$count} product(s).");
                }
                break;

            case 'delete':
                $products = Product::whereIn('id', $ids)->get();
                $posItemIds = $products->pluck('pos_item_id')->filter()->values()->all();
                $variantPosItemIds = ProductVariantMapping::whereIn('product_id', $ids)->pluck('pos_item_id')->filter()->values()->all();

                Product::whereIn('id', $ids)->delete();

                $allPosItemIds = array_values(array_unique(array_merge($posItemIds, $variantPosItemIds)));
                if (!empty($allPosItemIds)) {
                    SyncedPosItem::whereIn('pos_item_id', $allPosItemIds)->update(['mapping_status' => 'unmapped']);
                    $this->apiClient->unregisterMappings($allPosItemIds);
                }

                try {
                    $this->catalogService->reconcileAllMappings();
                } catch (\Throwable $e) {
                    // Non-blocking reconciliation
                }

                ActivityLogger::product("Bulk deleted {$count} product(s)", ['ids' => $ids]);
                return back()->with('success', "Successfully deleted {$count} product(s).");
        }

        return back()->with('error', 'No action performed.');
    }
}
