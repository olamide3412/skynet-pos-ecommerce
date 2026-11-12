<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductVariantMapping;
use App\Services\ActivityLogger;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventoryAdjustmentController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Show dedicated Stock Adjustment page.
     */
    public function index(Request $request): Response
    {
        // Pre-selected product if requested from elsewhere (e.g. products table or ledger)
        $selectedProduct = null;
        if ($request->filled('product_id')) {
            $selectedProduct = Product::where('id', $request->input('product_id'))
                ->where('inventory_source', 'ecommerce')
                ->select('id', 'name', 'sku', 'stock', 'price', 'cost_price', 'image', 'inventory_source')
                ->with(['variantMappings' => function ($v) {
                    $v->where('inventory_source', 'ecommerce')
                        ->select('id', 'product_id', 'variant_label', 'local_stock');
                }])
                ->first();
        }

        // Initial lightweight batch of 25 Ecommerce products for the picker
        $initialProducts = Product::where('inventory_source', 'ecommerce')
            ->select('id', 'name', 'sku', 'stock', 'price', 'cost_price', 'image', 'inventory_source')
            ->with(['variantMappings' => function ($v) {
                $v->where('inventory_source', 'ecommerce')
                    ->select('id', 'product_id', 'variant_label', 'local_stock');
            }])
            ->orderBy('name')
            ->limit(25)
            ->get();

        // Recent manual adjustments for instant audit feedback (paginated)
        $recentAdjustments = InventoryLog::where('action_type', 'adjustment')
            ->with(['product:id,name,sku,image', 'variantMapping:id,variant_label', 'user:id,name'])
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Inventory/Adjustments', [
            'initialProducts'   => $initialProducts,
            'selectedProduct'   => $selectedProduct,
            'recentAdjustments' => $recentAdjustments,
        ]);
    }

    /**
     * Perform an authoritative stock adjustment for an Ecommerce Only product.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'variant_id' => 'nullable|integer|exists:product_variant_mappings,id',
            'mode'       => 'required|in:add,subtract,set',
            'quantity'   => 'required|integer|min:0',
            'reason'     => 'required|string|max:100',
            'notes'      => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $product = Product::where('id', $validated['product_id'])->lockForUpdate()->firstOrFail();

            // Strict boundary: Only Ecommerce items can be manually adjusted
            if ($product->inventory_source === 'pos' && !empty($product->pos_item_id)) {
                return back()->with('error', "Physical POS items cannot be manually adjusted here. Please adjust stock on the physical POS machine.");
            }

            $variant = null;
            if (!empty($validated['variant_id'])) {
                $variant = ProductVariantMapping::where('id', $validated['variant_id'])
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();
            }

            $prevStock = $variant ? (int) $variant->local_stock : (int) $product->stock;
            $qty = (int) $validated['quantity'];

            switch ($validated['mode']) {
                case 'add':
                    if ($qty <= 0) {
                        return back()->with('error', 'Quantity to add must be greater than 0.');
                    }
                    $newStock = $prevStock + $qty;
                    $qtyChange = +$qty;
                    break;

                case 'subtract':
                    if ($qty <= 0) {
                        return back()->with('error', 'Quantity to subtract must be greater than 0.');
                    }
                    if ($qty > $prevStock) {
                        return back()->with('error', "Cannot subtract {$qty} units; current available stock is {$prevStock}.");
                    }
                    $newStock = $prevStock - $qty;
                    $qtyChange = -$qty;
                    break;

                case 'set':
                    $newStock = $qty;
                    $qtyChange = $newStock - $prevStock;
                    break;

                default:
                    return back()->with('error', 'Invalid adjustment mode.');
            }

            if ($qtyChange === 0) {
                return back()->with('info', 'Stock count was not changed.');
            }

            // Update product or variant stock
            if ($variant) {
                $variant->update(['local_stock' => $newStock]);
            } else {
                $product->update(['stock' => $newStock]);
            }

            // Record immutable ledger entry
            $this->inventoryService->recordLog(
                product: $product,
                actionType: 'adjustment',
                quantityChange: $qtyChange,
                previousStock: $prevStock,
                newStock: $newStock,
                reason: $validated['reason'],
                notes: $validated['notes'] ?? null,
                userId: Auth::id(),
                referenceType: 'Manual Adjustment',
                variantMappingId: $variant?->id
            );

            // Audit log
            $changeStr = ($qtyChange > 0 ? "+{$qtyChange}" : "{$qtyChange}");
            ActivityLogger::product(
                "Manual stock adjustment for '{$product->name}': {$prevStock} → {$newStock} ({$changeStr}). Reason: {$validated['reason']}",
                [
                    'product_id'     => $product->id,
                    'variant_id'     => $variant?->id,
                    'mode'           => $validated['mode'],
                    'previous_stock' => $prevStock,
                    'new_stock'      => $newStock,
                    'quantity'       => $qty,
                    'reason'         => $validated['reason'],
                    'notes'          => $validated['notes'] ?? null,
                ]
            );

            return back()->with('success', "Stock for '{$product->name}' successfully adjusted ({$prevStock} → {$newStock}).");
        });
    }
}
