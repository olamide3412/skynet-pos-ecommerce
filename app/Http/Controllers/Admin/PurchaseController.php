<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariantMapping;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Services\ActivityLogger;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Display a listing of historical stock purchases.
     */
    public function index(Request $request): Response
    {
        $query = Purchase::with(['user:id,name', 'items.product:id,name,sku,image'])->latest('purchase_date')->latest('id');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('purchase_number', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('invoice_reference', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('payment_status')) {
            $query->where('payment_status', $status);
        }

        if ($startDate = $request->input('start_date')) {
            $query->whereDate('purchase_date', '>=', $startDate);
        }

        if ($endDate = $request->input('end_date')) {
            $query->whereDate('purchase_date', '<=', $endDate);
        }

        if ($productId = $request->input('product_id')) {
            $query->whereHas('items', function ($q) use ($productId) {
                $q->where('product_id', $productId);
            });
        }

        $purchases = $query->paginate(20)->withQueryString();

        // Selected product if filtered
        $selectedProduct = null;
        if ($request->filled('product_id')) {
            $selectedProduct = Product::select('id', 'name', 'sku', 'product_number', 'stock', 'inventory_source')
                ->find($request->input('product_id'));
        }

        // Lightweight initial batch of 25 products for instant filter picker display
        $initialProducts = Product::select('id', 'name', 'sku', 'product_number', 'stock', 'inventory_source')
            ->orderBy('name')
            ->limit(25)
            ->get();

        // Summary metrics
        $metrics = [
            'total_purchases_count' => Purchase::count(),
            'total_spend'           => (float) Purchase::sum('total_cost'),
            'total_units_received'  => (int) Purchase::sum('total_items_count'),
            'this_month_spend'      => (float) Purchase::whereYear('purchase_date', now()->year)
                ->whereMonth('purchase_date', now()->month)
                ->sum('total_cost'),
        ];

        return Inertia::render('Admin/Purchases/Index', [
            'purchases'       => $purchases,
            'filters'         => $request->only(['search', 'payment_status', 'product_id', 'start_date', 'end_date']),
            'initialProducts' => $initialProducts,
            'selectedProduct' => $selectedProduct,
            'metrics'         => $metrics,
        ]);
    }

    /**
     * Show form to receive new inventory from suppliers.
     */
    public function create(): Response
    {
        // Lightweight initial batch of 25 Ecommerce products for instant line item picker display
        $initialProducts = Product::where('inventory_source', 'ecommerce')
            ->select('id', 'name', 'sku', 'price', 'cost_price', 'stock', 'image', 'inventory_source')
            ->with(['variantMappings' => function ($q) {
                $q->where('inventory_source', 'ecommerce')
                    ->select('id', 'product_id', 'variant_label', 'local_stock', 'local_price');
            }])
            ->orderBy('name')
            ->limit(25)
            ->get();

        // Recent supplier names for quick autocomplete
        $recentSuppliers = Purchase::distinct()
            ->latest('id')
            ->limit(20)
            ->pluck('supplier_name');

        return Inertia::render('Admin/Purchases/Create', [
            'eligibleProducts' => $initialProducts,
            'initialProducts'  => $initialProducts,
            'recentSuppliers'  => $recentSuppliers,
            'suggestedPoNumber' => 'PO-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
        ]);
    }

    /**
     * AJAX search products for Purchase creation or filtering.
     * Returns matching products capped at 25 items to prevent heavy loads when catalog has 2000+ items.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));
        $ecommerceOnly = $request->boolean('ecommerce_only', true);

        $query = Product::select('id', 'name', 'sku', 'product_number', 'stock', 'cost_price', 'price', 'image', 'inventory_source');

        if ($ecommerceOnly) {
            $query->where('inventory_source', 'ecommerce');
        }

        $query->with(['variantMappings' => function ($v) {
            $v->where('inventory_source', 'ecommerce')
                ->select('id', 'product_id', 'variant_label', 'local_stock', 'local_price');
        }]);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('product_number', 'like', "%{$q}%")
                    ->orWhere('id', $q);
            });
        }

        $products = $query->orderBy('name')
            ->limit(25)
            ->get();

        return response()->json([
            'products' => $products,
        ]);
    }

    /**
     * Store and receive new stock items into inventory.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_number'   => 'nullable|string|max:100|unique:purchases,purchase_number',
            'supplier_name'     => 'required|string|max:255',
            'invoice_reference' => 'nullable|string|max:100',
            'purchase_date'     => 'required|date',
            'payment_status'    => 'required|in:paid,pending,partial',
            'notes'             => 'nullable|string|max:1000',
            'items'             => 'required|array|min:1',
            'items.*.product_id'=> 'required|integer|exists:products,id',
            'items.*.variant_id'=> 'nullable|integer|exists:product_variant_mappings,id',
            'items.*.quantity'  => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        // Boundary verification: ensure no physical POS products are submitted
        foreach ($validated['items'] as $index => $itemData) {
            $checkProduct = Product::find($itemData['product_id']);
            if ($checkProduct && $checkProduct->inventory_source === 'pos' && !empty($checkProduct->pos_item_id)) {
                return back()->withErrors([
                    "items.{$index}.product_id" => "Product '{$checkProduct->name}' is managed by POS. Physical inventory must be received on your POS terminal.",
                ])->withInput();
            }
        }

        return DB::transaction(function () use ($validated) {
            $poNumber = !empty($validated['purchase_number'])
                ? trim($validated['purchase_number'])
                : ('PO-' . date('Ymd') . '-' . strtoupper(Str::random(5)));

            $totalCost = 0.0;
            $totalQty = 0;

            foreach ($validated['items'] as $item) {
                $subtotal = (int) $item['quantity'] * (float) $item['unit_cost'];
                $totalCost += $subtotal;
                $totalQty += (int) $item['quantity'];
            }

            $purchase = Purchase::create([
                'purchase_number'   => $poNumber,
                'supplier_name'     => trim($validated['supplier_name']),
                'invoice_reference' => !empty($validated['invoice_reference']) ? trim($validated['invoice_reference']) : null,
                'user_id'           => Auth::id(),
                'purchase_date'     => $validated['purchase_date'],
                'total_cost'        => $totalCost,
                'total_items_count' => $totalQty,
                'payment_status'    => $validated['payment_status'],
                'notes'             => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $product = Product::where('id', $item['product_id'])->lockForUpdate()->firstOrFail();

                // Boundary verification: must be ecommerce
                if ($product->inventory_source === 'pos' && !empty($product->pos_item_id)) {
                    continue; // Skip any attempted POS lines
                }

                $variant = null;
                if (!empty($item['variant_id'])) {
                    $variant = ProductVariantMapping::where('id', $item['variant_id'])
                        ->where('product_id', $product->id)
                        ->lockForUpdate()
                        ->first();
                }

                $qty = (int) $item['quantity'];
                $unitCost = (float) $item['unit_cost'];
                $subtotal = $qty * $unitCost;

                PurchaseItem::create([
                    'purchase_id'                => $purchase->id,
                    'product_id'                 => $product->id,
                    'product_variant_mapping_id' => $variant?->id,
                    'quantity'                   => $qty,
                    'unit_cost'                  => $unitCost,
                    'subtotal'                   => $subtotal,
                ]);

                // Update inventory and cost price (latest purchase price)
                $prevStock = $variant ? (int) $variant->local_stock : (int) $product->stock;
                $newStock = $prevStock + $qty;

                if ($variant) {
                    $variant->update(['local_stock' => $newStock]);
                } else {
                    $product->update([
                        'stock'      => $newStock,
                        'cost_price' => $unitCost, // Update latest purchase cost
                    ]);
                }

                // Record immutable inventory ledger
                $this->inventoryService->recordLog(
                    product: $product,
                    actionType: 'purchase',
                    quantityChange: +$qty,
                    previousStock: $prevStock,
                    newStock: $newStock,
                    reason: 'Supplier Stock Delivery',
                    notes: "PO #{$purchase->purchase_number} from {$purchase->supplier_name}" . ($purchase->invoice_reference ? " (Inv: {$purchase->invoice_reference})" : ''),
                    userId: Auth::id(),
                    referenceType: Purchase::class,
                    referenceId: $purchase->id,
                    referenceNumber: $purchase->purchase_number,
                    unitCost: $unitCost,
                    variantMappingId: $variant?->id
                );
            }

            ActivityLogger::product(
                "Recorded supplier purchase '{$purchase->purchase_number}' from {$purchase->supplier_name} ({$totalQty} units, ₦" . number_format($totalCost, 2) . ")",
                [
                    'purchase_id'       => $purchase->id,
                    'purchase_number'   => $purchase->purchase_number,
                    'supplier_name'     => $purchase->supplier_name,
                    'total_cost'        => $totalCost,
                    'total_items_count' => $totalQty,
                ]
            );

            return redirect()->route('admin.purchases.index')
                ->with('success', "Stock purchase '{$purchase->purchase_number}' successfully recorded. Inventory has been restocked.");
        });
    }

    /**
     * View purchase receipt and itemized breakdown.
     */
    public function show(Purchase $purchase): Response
    {
        $purchase->load(['user:id,name,email', 'items.product:id,name,sku,image,price,cost_price', 'items.variantMapping:id,variant_label']);

        return Inertia::render('Admin/Purchases/Show', [
            'purchase' => $purchase,
        ]);
    }
}
