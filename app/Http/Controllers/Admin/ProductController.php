<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPosUnit;
use App\Models\SyncedPosItem;
use App\Services\PosIntegration\PosApiClient;
use App\Services\PosIntegration\PosCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProductController extends Controller
{
    protected PosCatalogService $catalogService;
    protected PosApiClient $apiClient;

    public function __construct(PosCatalogService $catalogService, PosApiClient $apiClient)
    {
        $this->catalogService = $catalogService;
        $this->apiClient = $apiClient;
    }

    public function index(Request $request)
    {
        $query = Product::with(['category', 'posUnits', 'syncedPosItem'])->latest();

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
            ->select('pos_item_id', 'pos_item_name', 'standard_price', 'effective_availability', 'base_unit_name', 'mapping_status')
            ->get();

        return Inertia::render('Admin/Products/Index', [
            'products' => $query->paginate(15)->withQueryString(),
            'categories' => Category::whereNull('parent_id')
                ->with(['children' => fn($q) => $q->orderBy('name')])
                ->with('attributes')
                ->orderBy('name')
                ->get(),
            'synced_pos_items' => $syncedItems,
            'store_settings' => \App\Models\StoreSetting::allAsArray(),
            'filters' => $request->only(['search', 'category_id', 'status', 'inventory_source'])
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
            'image'                => 'nullable|image|max:3072',
            'images'               => 'nullable|array|max:10',
            'images.*'             => 'image|max:3072',
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
            $validated['pos_sync_status'] = null;
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

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        } else {
            unset($validated['image']);
        }

        if ($request->hasFile('images')) {
            $paths = [];
            foreach ($request->file('images') as $file) {
                $paths[] = $file->store('products', 'public');
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
        }

        return back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $posItemId = $product->pos_item_id;

        $product->delete();

        if ($posItemId) {
            SyncedPosItem::where('pos_item_id', $posItemId)->update(['mapping_status' => 'unmapped']);
            $this->apiClient->unregisterMappings([$posItemId]);
        }

        return back()->with('success', 'Product deleted.');
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
                return back()->with('success', "Moved {$count} product(s) to {$category->name}.");

            case 'update_status':
                $status = filter_var($request->input('status', true), FILTER_VALIDATE_BOOLEAN);
                Product::whereIn('id', $ids)->update(['status' => $status]);
                $label = $status ? 'Active' : 'Inactive';
                return back()->with('success', "Marked {$count} product(s) as {$label}.");

            case 'update_featured':
                $featured = filter_var($request->input('is_featured', true), FILTER_VALIDATE_BOOLEAN);
                Product::whereIn('id', $ids)->update(['is_featured' => $featured]);
                $label = $featured ? 'Featured' : 'Standard';
                return back()->with('success', "Updated featured status for {$count} product(s).");

            case 'update_new':
                $isNew = filter_var($request->input('is_new', true), FILTER_VALIDATE_BOOLEAN);
                Product::whereIn('id', $ids)->update(['is_new' => $isNew]);
                $label = $isNew ? 'New' : 'Standard';
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
                    return back()->with('success', "Updated prices for {$count} product(s).");
                }
                break;

            case 'delete':
                $products = Product::whereIn('id', $ids)->get();
                $posItemIds = $products->pluck('pos_item_id')->filter()->values()->all();

                Product::whereIn('id', $ids)->delete();

                if (!empty($posItemIds)) {
                    SyncedPosItem::whereIn('pos_item_id', $posItemIds)->update(['mapping_status' => 'unmapped']);
                    $this->apiClient->unregisterMappings($posItemIds);
                }

                return back()->with('success', "Successfully deleted {$count} product(s).");
        }

        return back()->with('error', 'No action performed.');
    }
}
