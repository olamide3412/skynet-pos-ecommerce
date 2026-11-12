<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPosUnit;
use App\Models\ProductVariantMapping;
use App\Models\SyncedPosItem;
use App\Services\ActivityLogger;
use App\Services\Brands\PosBrandResolver;
use App\Services\ImageOptimizer;
use App\Services\PosIntegration\PosApiClient;
use App\Services\PosIntegration\PosCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $query = Product::with(['category', 'brand', 'posUnits', 'syncedPosItem', 'variantMappings.syncedPosItem']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('url_key', 'like', "%{$search}%")
                    ->orWhereHas('brand', fn ($bq) => $bq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($brandId = $request->input('brand_id')) {
            if ($brandId === 'none') {
                $query->whereNull('brand_id');
            } else {
                $query->where('brand_id', $brandId);
            }
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status === 'active' ? 1 : 0);
        }

        if ($invSource = $request->input('inventory_source')) {
            if (in_array($invSource, ['ecommerce', 'pos'])) {
                $query->where('inventory_source', $invSource);
            }
        }

        $sort = $request->input('sort', 'latest');
        match ($sort) {
            'name_asc'   => $query->orderBy('name', 'asc'),
            'name_desc'  => $query->orderBy('name', 'desc'),
            'qty_desc', 'stock_desc' => $query->orderBy('stock', 'desc'),
            'qty_asc', 'stock_asc'   => $query->orderBy('stock', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'price_asc'  => $query->orderBy('price', 'asc'),
            'oldest'     => $query->oldest(),
            default      => $query->latest(),
        };

        // List synced POS items for selection in the product creation modal
        $syncedItems = SyncedPosItem::where('is_active', true)
            ->select('pos_item_id', 'pos_item_name', 'pos_category_name', 'pos_brand_id', 'pos_brand_name', 'sku', 'barcode', 'item_reference', 'standard_price', 'buying_price', 'effective_availability', 'base_unit_name', 'mapping_status')
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
            'brands' => Brand::ordered()->get(['id', 'name', 'slug', 'logo', 'is_active', 'pos_brand_id']),
            'synced_pos_items' => $syncedItems,
            'store_settings' => \App\Models\StoreSetting::allAsArray(),
            'filters' => array_merge($request->only(['search', 'category_id', 'brand_id', 'status', 'inventory_source', 'sort']), [
                'per_page' => (string) $perPageInput,
            ]),
        ]);
    }

    /** AJAX: search products for the related-products picker */
    public function searchRelated(Request $request)
    {
        $q = trim($request->get('q', ''));
        $exclude = (int) $request->get('exclude', 0);
        $categoryId = (int) $request->get('category_id', 0);

        $query = Product::select('id', 'name', 'image', 'url_key', 'category_id');
        if ($q) {
            $query->where('name', 'like', '%' . $q . '%');
        }
        if ($exclude) {
            $query->where('id', '!=', $exclude);
        }
        if ($categoryId) {
            $query->orderByRaw("CASE WHEN category_id = ? THEN 0 ELSE 1 END", [$categoryId]);
        }
        $query->orderBy('name');

        return response()->json($query->limit(20)->get());
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
            'brand_id'             => 'nullable|exists:brands,id',
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
            'variants'             => 'nullable',
            'attributes'           => 'nullable',
            'image'                => 'nullable|image|max:10240',
            'images'               => 'nullable|array|max:10',
            'images.*'             => 'image|max:10240',
            'existing_images'      => 'nullable',
            'remove_thumbnail'     => 'nullable',
            'compress_images'      => 'nullable',
        ];
    }

    private function processRequest(Request $request, array $validated, ?Product $product = null): array
    {
        $validated['slug'] = Str::slug($validated['name']) . '-' . uniqid();
        $validated['brand_id'] = !empty($validated['brand_id']) ? (int) $validated['brand_id'] : null;

        // Booleans sent as strings from FormData
        foreach (['is_new', 'is_featured', 'visible_individually', 'status', 'show_stock_level'] as $bool) {
            $validated[$bool] = filter_var($validated[$bool] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        // If POS linked, load values from SyncedPosItem
        if (($validated['inventory_source'] ?? '') === 'pos' && !empty($validated['pos_item_id'])) {
            $syncedItem = SyncedPosItem::where('pos_item_id', $validated['pos_item_id'])->first();
            if ($syncedItem) {
                $validated['price'] = $syncedItem->standard_price;
                if ($syncedItem->buying_price !== null) {
                    $validated['cost_price'] = $syncedItem->buying_price;
                }
                $validated['stock'] = (int) floor($syncedItem->effective_availability);
                $validated['pos_item_name_snapshot'] = $syncedItem->pos_item_name;
                $validated['pos_base_unit_name'] = $syncedItem->base_unit_name;
                $validated['pos_sync_status'] = 'synced';
                $validated['last_pos_sync_at'] = now();

                if (empty($validated['brand_id']) && (!empty($syncedItem->pos_brand_id) || !empty($syncedItem->pos_brand_name))) {
                    $validated['brand_id'] = (new PosBrandResolver())->resolve($syncedItem->pos_brand_id, $syncedItem->pos_brand_name);
                }
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
            $inputVariants = $request->input('variants');
            $validated['variants'] = is_array($inputVariants) ? $inputVariants : (json_decode($inputVariants, true) ?: []);
        }

        if ($request->has('attributes') && $request->input('attributes')) {
            $inputAttrs = $request->input('attributes');
            $validated['attributes'] = is_array($inputAttrs) ? $inputAttrs : (json_decode($inputAttrs, true) ?: []);
        }

        $shouldCompress = filter_var($request->input('compress_images', true), FILTER_VALIDATE_BOOLEAN);
        unset($validated['compress_images']);

        $baseSlug = !empty($validated['url_key'])
            ? Str::slug($validated['url_key'])
            : Str::slug($validated['name'] ?? 'product');

        // Handle thumbnail image removal or upload
        $removeThumbnail = filter_var($request->input('remove_thumbnail', false), FILTER_VALIDATE_BOOLEAN);
        unset($validated['remove_thumbnail']);

        if ($removeThumbnail) {
            if ($product && $product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = null;
        } elseif ($request->hasFile('image')) {
            if ($product && $product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $this->imageOptimizer->store(
                file: $request->file('image'),
                directory: 'products',
                compress: $shouldCompress,
                slug: $baseSlug
            );
        } else {
            unset($validated['image']);
        }

        // Handle gallery images (retained existing + newly uploaded)
        $existingImagesInput = $request->input('existing_images');
        $hasExistingImagesField = $request->has('existing_images');
        $retainedGallery = [];

        if ($hasExistingImagesField) {
            if (is_string($existingImagesInput)) {
                $decoded = json_decode($existingImagesInput, true);
                $retainedGallery = is_array($decoded) ? $decoded : [];
            } elseif (is_array($existingImagesInput)) {
                $retainedGallery = $existingImagesInput;
            }
            $retainedGallery = array_values(array_filter($retainedGallery, 'is_string'));
        } elseif ($product && is_array($product->images)) {
            $retainedGallery = $product->images;
        }

        // Delete any gallery images that were removed by admin
        if ($product && is_array($product->images)) {
            $deletedGallery = array_diff($product->images, $retainedGallery);
            foreach ($deletedGallery as $delImg) {
                if (!empty($delImg) && Storage::disk('public')->exists($delImg)) {
                    Storage::disk('public')->delete($delImg);
                }
            }
        }

        $newGalleryPaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $file) {
                $gallerySlug = "{$baseSlug}-gallery-" . (count($retainedGallery) + $index + 1);
                $newGalleryPaths[] = $this->imageOptimizer->store(
                    file: $file,
                    directory: 'products',
                    compress: $shouldCompress,
                    slug: $gallerySlug
                );
            }
        }

        if ($hasExistingImagesField || $request->hasFile('images')) {
            $validated['images'] = array_values(array_merge($retainedGallery, $newGalleryPaths));
        } else {
            unset($validated['images']);
        }
        unset($validated['existing_images']);

        return $validated;
    }

    public function store(Request $request)
    {
        // Decode related_products JSON string before validation (FormData sends strings)
        if ($request->has('related_products') && is_string($request->related_products)) {
            $request->merge(['related_products' => json_decode($request->related_products, true) ?: []]);
        }

        $validated = $request->validate($this->rules());
        $validated = $this->processRequest($request, $validated, null);

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
        $validated = $this->processRequest($request, $validated, $product);

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
            $product->posUnits()->delete();
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
                        if (empty($product->brand_id) && !empty($standaloneProduct->brand_id)) {
                            $product->updateQuietly(['brand_id' => $standaloneProduct->brand_id]);
                        }
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
            'action'          => 'required|string|in:update_category,update_brand,update_status,update_featured,update_new,update_prices,delete,move_to_ecommerce',
            'category_id'     => 'nullable|required_if:action,update_category|exists:categories,id',
            'brand_id'        => 'nullable|exists:brands,id',
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

            case 'update_brand':
                $targetBrandId = !empty($validated['brand_id']) ? (int) $validated['brand_id'] : null;
                Product::whereIn('id', $ids)->update(['brand_id' => $targetBrandId]);
                $brand = $targetBrandId ? Brand::find($targetBrandId) : null;
                $brandLabel = $brand ? $brand->name : 'No Brand (Cleared)';
                ActivityLogger::product("Bulk updated brand for {$count} product(s) to '{$brandLabel}'", ['ids' => $ids, 'brand_id' => $targetBrandId]);
                return back()->with('success', "Updated brand for {$count} product(s) to {$brandLabel}.");

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

            case 'move_to_ecommerce':
                $posProducts = Product::whereIn('id', $ids)
                    ->where('inventory_source', 'pos')
                    ->get();

                if ($posProducts->isEmpty()) {
                    return back()->with('error', 'None of the selected products are POS-linked items.');
                }

                $posItemIds = $posProducts->pluck('pos_item_id')->filter()->values()->all();
                $variantPosItemIds = ProductVariantMapping::whereIn('product_id', $posProducts->pluck('id'))->pluck('pos_item_id')->filter()->values()->all();
                $allPosItemIds = array_values(array_unique(array_merge($posItemIds, $variantPosItemIds)));

                foreach ($posProducts as $product) {
                    $product->posUnits()->delete();
                    $product->update([
                        'inventory_source' => 'ecommerce',
                        'pos_item_id'      => null,
                        'pos_sync_status'  => 'synced',
                    ]);
                }

                if (!empty($allPosItemIds)) {
                    SyncedPosItem::whereIn('pos_item_id', $allPosItemIds)->update(['mapping_status' => 'unmapped']);
                    try {
                        $this->apiClient->unregisterMappings($allPosItemIds);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning("Failed to unregister mappings during bulk move to ecommerce: {$e->getMessage()}");
                    }
                }

                try {
                    $this->catalogService->reconcileAllMappings();
                } catch (\Throwable $e) {
                    // Non-blocking reconciliation
                }

                $convertedCount = $posProducts->count();
                ActivityLogger::product("Bulk moved {$convertedCount} POS product(s) to Ecommerce Only", [
                    'ids' => $posProducts->pluck('id')->all(),
                    'pos_item_ids' => $allPosItemIds,
                ]);

                return back()->with('success', "Successfully moved {$convertedCount} POS product(s) to Ecommerce Only and released POS locks.");

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

    /**
     * Export products to CSV (Ecommerce Items Only, POS Items Only, or Both).
     */
    public function export(Request $request): StreamedResponse
    {
        $source = strtolower($request->input('source', 'both'));
        if (!in_array($source, ['both', 'all', 'ecommerce', 'pos'])) {
            $source = 'both';
        }

        $query = Product::with(['category', 'brand', 'syncedPosItem', 'variantMappings.syncedPosItem'])->latest();

        if ($source === 'ecommerce') {
            $query->where('inventory_source', 'ecommerce');
        } elseif ($source === 'pos') {
            $query->where('inventory_source', 'pos');
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('url_key', 'like', "%{$search}%")
                    ->orWhereHas('brand', fn ($bq) => $bq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($catId = $request->input('category_id')) {
            $query->where('category_id', $catId);
        }

        if ($brandId = $request->input('brand_id')) {
            if ($brandId === 'none') {
                $query->whereNull('brand_id');
            } else {
                $query->where('brand_id', $brandId);
            }
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status === 'active' ? 1 : 0);
        }

        $sort = $request->input('sort', 'latest');
        match ($sort) {
            'name_asc'   => $query->orderBy('name', 'asc'),
            'name_desc'  => $query->orderBy('name', 'desc'),
            'qty_desc', 'stock_desc' => $query->orderBy('stock', 'desc'),
            'qty_asc', 'stock_asc'   => $query->orderBy('stock', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'price_asc'  => $query->orderBy('price', 'asc'),
            'oldest'     => $query->oldest(),
            default      => $query->latest(),
        };

        $sourceLabel = match ($source) {
            'ecommerce' => 'ecommerce_only',
            'pos' => 'pos_only',
            default => 'all',
        };

        $filename = "products_export_{$sourceLabel}_" . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        ActivityLogger::product("Exported products to CSV (source: {$source})", [
            'source' => $source,
        ]);

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel UTF-8 display compatibility
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'ID',
                'Name',
                'SKU',
                'Inventory Source',
                'POS Item ID',
                'Synced POS Item Name',
                'Category',
                'Brand',
                'Price (NGN)',
                'Cost Price (NGN)',
                'Special Price (NGN)',
                'Stock',
                'Status',
                'Is Featured',
                'Variants Count',
                'Variant Details',
                'Created At',
                'URL Key',
                'Thumbnail',
            ]);

            $query->chunk(200, function ($products) use ($handle) {
                foreach ($products as $p) {
                    $invSourceLabel = $p->inventory_source === 'pos' ? 'Physical POS Branch Item' : 'Online Warehouse (Ecommerce Only)';
                    $statusLabel = $p->status ? 'Active' : 'Inactive';
                    $featuredLabel = $p->is_featured ? 'Yes' : 'No';
                    $posName = $p->syncedPosItem ? $p->syncedPosItem->pos_item_name : ($p->pos_item_name_snapshot ?? '');

                    $thumbnailUrl = '';
                    if (!empty($p->image)) {
                        $thumbnailUrl = (str_starts_with($p->image, 'http://') || str_starts_with($p->image, 'https://'))
                            ? $p->image
                            : asset('storage/' . ltrim($p->image, '/'));
                    }

                    $variantSummary = '';
                    if ($p->variantMappings && $p->variantMappings->isNotEmpty()) {
                        $variantSummary = $p->variantMappings->map(function ($v) {
                            $src = $v->inventory_source === 'pos' ? "POS #{$v->pos_item_id}" : "Ecommerce";
                            $price = $v->local_price ? "₦" . number_format((float) $v->local_price, 2) : "Standard";
                            return "{$v->variant_label} [{$src}, {$price}, Stock: {$v->local_stock}]";
                        })->join('; ');
                    }

                    fputcsv($handle, [
                        $p->id,
                        $p->name,
                        $p->sku ?? '',
                        $invSourceLabel,
                        $p->pos_item_id ?? '',
                        $posName,
                        $p->category?->name ?? 'Uncategorized',
                        $p->brand?->name ?? 'No Brand',
                        number_format((float) ($p->price ?? 0), 2, '.', ''),
                        $p->cost_price !== null ? number_format((float) $p->cost_price, 2, '.', '') : '',
                        $p->special_price !== null ? number_format((float) $p->special_price, 2, '.', '') : '',
                        $p->stock ?? 0,
                        $statusLabel,
                        $featuredLabel,
                        $p->variantMappings?->count() ?? 0,
                        $variantSummary,
                        $p->created_at?->format('Y-m-d H:i:s') ?? '',
                        $p->url_key ?? '',
                        $thumbnailUrl,
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Import products from CSV (Both, Ecommerce Only, or POS Only).
     * Missing or unverified POS items are skipped with clear feedback.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file'        => 'required|file|mimes:csv,txt|max:10240',
            'import_mode' => 'nullable|string|in:both,ecommerce,pos',
        ]);

        $mode = strtolower($request->input('import_mode', 'both'));
        $file = $request->file('file');

        $path = $file->getRealPath();
        $handle = fopen($path, 'r');
        if (!$handle) {
            return back()->with('error', 'Could not open uploaded CSV file.');
        }

        // Strip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Read header
        $headerRow = fgetcsv($handle);
        if (!$headerRow) {
            fclose($handle);
            return back()->with('error', 'The uploaded CSV file is empty.');
        }

        // Normalize header keys
        $headerMap = [];
        foreach ($headerRow as $index => $col) {
            $normalized = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', str_replace([' ', '-', '/'], '_', $col))));
            $headerMap[$normalized] = $index;
        }

        // Helper to get column value from row
        $getVal = function ($row, array $possibleKeys) use ($headerMap) {
            foreach ($possibleKeys as $key) {
                if (isset($headerMap[$key]) && isset($row[$headerMap[$key]])) {
                    $v = trim($row[$headerMap[$key]]);
                    if ($v !== '') return $v;
                }
            }
            return null;
        };

        // Cache categories and brands for high-speed resolution
        $categoriesByName = Category::all()->keyBy(fn ($c) => strtolower(trim($c->name)));
        $categoriesById   = Category::all()->keyBy('id');
        $brandsByName     = Brand::all()->keyBy(fn ($b) => strtolower(trim($b->name)));
        $brandsById       = Brand::all()->keyBy('id');

        $defaultCategory = Category::first();
        if (!$defaultCategory) {
            $defaultCategory = Category::create([
                'name' => 'General',
                'slug' => 'general',
            ]);
        }

        $importedCount = 0;
        $updatedCount  = 0;
        $skippedPosCount = 0;
        $skippedPosItems = [];
        $skippedOtherCount = 0;

        while (($row = fgetcsv($handle)) !== false) {
            // Skip entirely blank rows
            if (empty(array_filter($row, fn ($val) => trim($val) !== ''))) {
                continue;
            }

            $name = $getVal($row, ['name', 'product_name', 'title']);
            if (!$name) {
                $skippedOtherCount++;
                continue;
            }

            $rawSource = strtolower($getVal($row, ['inventory_source', 'source', 'source_type', 'type']) ?? '');
            $rawPosItemId = $getVal($row, ['pos_item_id', 'pos_id', 'positemid']);
            $posItemId = ($rawPosItemId !== null && is_numeric($rawPosItemId) && (int) $rawPosItemId > 0)
                ? (int) $rawPosItemId
                : null;

            // Determine effective source based on mode and row
            if ($mode === 'ecommerce') {
                $targetSource = 'ecommerce';
                $posItemId = null; // Do not link to POS in Ecommerce-only mode
            } elseif ($mode === 'pos') {
                $targetSource = 'pos';
            } else { // 'both'
                if ($posItemId || str_contains($rawSource, 'pos') || str_contains($rawSource, 'branch')) {
                    $targetSource = 'pos';
                } else {
                    $targetSource = 'ecommerce';
                }
            }

            // POS ITEM AVAILABILITY CHECK & SKIP LOGIC
            if ($targetSource === 'pos') {
                if (!$posItemId) {
                    $skippedPosCount++;
                    $skippedPosItems[] = "{$name} (missing POS Item ID)";
                    continue;
                }

                $syncedItem = SyncedPosItem::where('pos_item_id', $posItemId)->first();
                if (!$syncedItem) {
                    // POS item is not found or not available in the connected branch
                    $skippedPosCount++;
                    $skippedPosItems[] = "{$name} (POS #{$posItemId} not found in branch)";
                    continue;
                }
            } else {
                $syncedItem = null;
            }

            // Category Resolution
            $catVal = $getVal($row, ['category', 'category_id', 'category_name']);
            $category = null;
            if ($catVal) {
                if (is_numeric($catVal) && isset($categoriesById[(int) $catVal])) {
                    $category = $categoriesById[(int) $catVal];
                } else {
                    $lowerCat = strtolower($catVal);
                    $category = $categoriesByName[$lowerCat] ?? null;
                }
            }
            if (!$category) {
                $category = $defaultCategory;
            }

            // Brand Resolution
            $brandVal = $getVal($row, ['brand', 'brand_id', 'brand_name']);
            $brand = null;
            if ($brandVal && strtolower($brandVal) !== 'no brand' && strtolower($brandVal) !== 'none') {
                if (is_numeric($brandVal) && isset($brandsById[(int) $brandVal])) {
                    $brand = $brandsById[(int) $brandVal];
                } else {
                    $lowerBrand = strtolower($brandVal);
                    $brand = $brandsByName[$lowerBrand] ?? null;
                }
            }

            // Price & Stock
            $rawPrice = $getVal($row, ['price_ngn', 'price', 'standard_price', 'regular_price']);
            $price = ($rawPrice !== null && is_numeric(str_replace(',', '', $rawPrice)))
                ? (float) str_replace(',', '', $rawPrice)
                : ($syncedItem ? (float) $syncedItem->standard_price : 0.0);

            $rawCostPrice = $getVal($row, ['cost_price_ngn', 'cost_price', 'buying_price']);
            $costPrice = ($rawCostPrice !== null && is_numeric(str_replace(',', '', $rawCostPrice)))
                ? (float) str_replace(',', '', $rawCostPrice)
                : ($syncedItem && $syncedItem->buying_price !== null ? (float) $syncedItem->buying_price : null);

            $rawSpecialPrice = $getVal($row, ['special_price_ngn', 'special_price', 'discount_price', 'sale_price']);
            $specialPrice = ($rawSpecialPrice !== null && is_numeric(str_replace(',', '', $rawSpecialPrice)))
                ? (float) str_replace(',', '', $rawSpecialPrice)
                : null;

            $rawStock = $getVal($row, ['stock', 'quantity', 'qty']);
            $stock = ($rawStock !== null && is_numeric($rawStock))
                ? (int) $rawStock
                : ($syncedItem ? (int) floor($syncedItem->effective_availability) : 0);

            // Status & Featured
            $rawStatus = strtolower($getVal($row, ['status', 'active', 'is_active']) ?? 'active');
            $status = !in_array($rawStatus, ['0', 'inactive', 'false', 'draft', 'no']);

            $rawFeatured = strtolower($getVal($row, ['is_featured', 'featured']) ?? 'no');
            $isFeatured = in_array($rawFeatured, ['1', 'yes', 'true', 'featured']);

            $sku = $getVal($row, ['sku', 'product_sku']);
            $productNumber = $getVal($row, ['product_number', 'item_code']);
            $rowUrlKey = $getVal($row, ['url_key', 'url', 'slug']);
            $existingId = $getVal($row, ['id', 'product_id']);

            $rawImage = $getVal($row, ['thumbnail', 'image', 'thumbnail_url', 'image_url', 'thumbnail_path']);
            $imagePath = null;
            if (!empty($rawImage)) {
                if (preg_match('#/storage/(.+)$#', $rawImage, $matches)) {
                    $imagePath = ltrim($matches[1], '/');
                } elseif (str_starts_with($rawImage, 'http://') || str_starts_with($rawImage, 'https://')) {
                    // Download external image if valid, otherwise fallback to URL
                    try {
                        $imgResponse = Http::timeout(6)->get($rawImage);
                        if ($imgResponse->successful()) {
                            $parsedPath = parse_url($rawImage, PHP_URL_PATH);
                            $ext = strtolower(pathinfo($parsedPath ?? '', PATHINFO_EXTENSION) ?: 'jpg');
                            $cleanExt = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? $ext : 'jpg';
                            $imgName = 'imported-' . Str::slug($name) . '-' . Str::random(6) . '.' . $cleanExt;
                            Storage::disk('public')->put("products/{$imgName}", $imgResponse->body());
                            $imagePath = "products/{$imgName}";
                        } else {
                            $imagePath = $rawImage;
                        }
                    } catch (\Throwable $e) {
                        $imagePath = $rawImage;
                    }
                } else {
                    $imagePath = ltrim($rawImage, '/');
                }
            }

            // Locate existing product if any
            $product = null;
            if ($existingId && is_numeric($existingId)) {
                $product = Product::find((int) $existingId);
            }
            if (!$product && $targetSource === 'pos' && $posItemId) {
                $product = Product::where('pos_item_id', $posItemId)->first();
            }
            if (!$product && $sku) {
                $product = Product::where('sku', $sku)->first();
            }

            if ($product) {
                // Update product
                $product->name = $name;
                $product->category_id = $category->id;
                if ($brand) $product->brand_id = $brand->id;
                $product->price = $price;
                if ($costPrice !== null) $product->cost_price = $costPrice;
                if ($specialPrice !== null) $product->special_price = $specialPrice;
                $product->stock = $stock;
                $product->status = $status;
                $product->is_featured = $isFeatured;
                $product->inventory_source = $targetSource;
                if ($targetSource === 'pos') {
                    $product->pos_item_id = $posItemId;
                    $product->pos_item_name_snapshot = $syncedItem->pos_item_name;
                    $product->pos_sync_status = 'synced';
                    $syncedItem->update(['mapping_status' => 'mapped']);
                }
                if ($sku && empty($product->sku)) $product->sku = $sku;
                if ($imagePath) $product->image = $imagePath;
                $product->save();
                $updatedCount++;
            } else {
                // Generate unique url_key & slug
                $baseKey = !empty($rowUrlKey) ? Str::slug($rowUrlKey) : Str::slug($name);
                if (empty($baseKey)) $baseKey = 'product-' . uniqid();
                $urlKey = $baseKey;
                $k = 1;
                while (Product::where('url_key', $urlKey)->exists()) {
                    $urlKey = $baseKey . '-' . $k++;
                }

                $newProduct = Product::create([
                    'name'                   => $name,
                    'slug'                   => $urlKey,
                    'url_key'                => $urlKey,
                    'sku'                    => $sku,
                    'product_number'         => $productNumber,
                    'category_id'            => $category->id,
                    'brand_id'               => $brand?->id,
                    'price'                  => $price,
                    'cost_price'             => $costPrice,
                    'special_price'          => $specialPrice,
                    'stock'                  => $stock,
                    'status'                 => $status,
                    'is_featured'            => $isFeatured,
                    'inventory_source'       => $targetSource,
                    'pos_item_id'            => $targetSource === 'pos' ? $posItemId : null,
                    'pos_item_name_snapshot' => $syncedItem?->pos_item_name,
                    'pos_sync_status'        => $targetSource === 'pos' ? 'synced' : 'synced',
                    'image'                  => $imagePath,
                ]);

                if ($targetSource === 'pos' && $syncedItem) {
                    $syncedItem->update(['mapping_status' => 'mapped']);
                }

                $importedCount++;
            }
        }

        fclose($handle);

        ActivityLogger::product("Imported products via CSV (mode: {$mode}). Created: {$importedCount}, Updated: {$updatedCount}, Skipped POS: {$skippedPosCount}", [
            'mode'            => $mode,
            'imported_count'  => $importedCount,
            'updated_count'   => $updatedCount,
            'skipped_pos'     => $skippedPosCount,
        ]);

        $summaryParts = [];
        if ($importedCount > 0) $summaryParts[] = "{$importedCount} product(s) created";
        if ($updatedCount > 0) $summaryParts[] = "{$updatedCount} product(s) updated";
        $successMsg = !empty($summaryParts)
            ? implode(' and ', $summaryParts) . ' successfully.'
            : 'Product import completed.';

        $warningMsg = null;
        if ($skippedPosCount > 0) {
            $preview = count($skippedPosItems) <= 3
                ? implode(', ', $skippedPosItems)
                : implode(', ', array_slice($skippedPosItems, 0, 3)) . " and " . ($skippedPosCount - 3) . " more";
            $warningMsg = "{$skippedPosCount} POS item(s) skipped (not found in connected POS branch): {$preview}.";
        }

        return back()
            ->with('success', $successMsg)
            ->with('warning', $warningMsg);
    }
}
