<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductPosUnit;
use App\Models\ProductVariantMapping;
use App\Models\StoreSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = $this->getCart($request);
        
        return Inertia::render('Frontend/Cart/Index', [
            'cart'   => $cart ? $cart->load(['items.product.posUnits', 'items.posUnit', 'items.variantMapping.syncedPosItem']) : null,
            'coupon' => $request->session()->get('applied_coupon'),
        ]);
    }

    public function store(Request $request)
    {
        if (!StoreSetting::getBool('shop_enabled', true)) {
            return back()->with('error', 'Online store shopping is temporarily closed. Please check back later.');
        }

        $request->validate([
            'product_id'                 => 'required|exists:products,id',
            'quantity'                   => 'required|integer|min:1',
            'product_pos_unit_id'        => 'nullable|exists:product_pos_units,id',
            'product_variant_mapping_id' => 'nullable|exists:product_variant_mappings,id',
            'variant'                    => 'nullable|string',
            'variant_key'                => 'nullable|string',
        ]);

        $product = Product::with(['posUnits', 'variantMappings.syncedPosItem'])->findOrFail($request->product_id);

        if (!$product->isAvailableForPurchase()) {
            return back()->with('error', 'This product is currently unavailable for purchase.');
        }

        $cart = $this->getOrCreateCart($request);

        // Resolve variant mapping if present
        $variantMapping = null;
        if ($request->product_variant_mapping_id) {
            $variantMapping = $product->variantMappings->firstWhere('id', $request->product_variant_mapping_id);
        } elseif ($request->variant) {
            $variantMapping = $product->variantMappings->first(function ($m) use ($request) {
                return $m->variant_label === $request->variant
                    || $m->variant_key === ($request->variant_key ?? $request->variant);
            });
        }

        // Resolve selling unit (selling units apply to the base product only, not variants)
        $selectedUnit = null;
        if (!$variantMapping && !$request->variant && $request->product_pos_unit_id) {
            $selectedUnit = $product->posUnits->firstWhere('id', $request->product_pos_unit_id);
            if (!$selectedUnit || !$selectedUnit->is_enabled_online) {
                return back()->with('error', 'The selected purchasing unit is not available.');
            }
        }

        $unitName = $selectedUnit ? $selectedUnit->display_label : ($product->pos_base_unit_name ?? 'Piece');
        $price = $product->getEffectivePriceForUnit($selectedUnit);

        // Check stock availability if product is ecommerce-managed
        if ($product->inventory_source === 'ecommerce') {
            $baseStock = $variantMapping && $variantMapping->local_stock !== null ? (int) $variantMapping->local_stock : (int) $product->stock;
            $unitMultiplier = $selectedUnit ? floatval($selectedUnit->quantity_in_base_units ?: 1.0) : 1.0;
            $maxAllowed = (int) floor($baseStock / ($unitMultiplier > 0 ? $unitMultiplier : 1.0));
            if ($maxAllowed < $request->quantity) {
                $targetLabel = $selectedUnit ? $unitName : 'unit(s)';
                return back()->with('error', "Only {$maxAllowed} {$targetLabel} available based on current warehouse stock.");
            }
        }

        if ($variantMapping) {
            if ($variantMapping->isPosControlled()) {
                $available = $variantMapping->syncedPosItem
                    ? (int) floor($variantMapping->syncedPosItem->effective_availability)
                    : (int) $variantMapping->local_stock;

                if ($available < $request->quantity) {
                    return back()->with('error', "The selected option '{$variantMapping->variant_label}' is currently out of stock in our branch.");
                }

                $price = $variantMapping->syncedPosItem?->standard_price ?? $variantMapping->local_price ?? $price;
            } elseif ($variantMapping->local_price !== null && $variantMapping->local_price > 0) {
                $price = floatval($variantMapping->local_price);
            }
        } elseif ($request->variant && $product->variants) {
            // Fallback for simple JSON variants
            $variantsArray = is_string($product->variants) ? json_decode($product->variants, true) : $product->variants;
            if (is_array($variantsArray)) {
                foreach ($variantsArray as $v) {
                    if (($v['name'] ?? '') === $request->variant && !empty($v['price'])) {
                        $price = floatval($v['price']);
                        break;
                    }
                }
            }
        }

        $cartItem = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_pos_unit_id', $selectedUnit?->id)
            ->where(function ($q) use ($request, $variantMapping) {
                if ($variantMapping) {
                    $q->where('product_variant_mapping_id', $variantMapping->id)
                      ->orWhere('variant', $request->variant);
                } else {
                    $q->where('variant', $request->variant);
                }
            })
            ->first();

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $request->quantity;

            if ($variantMapping && $variantMapping->isPosControlled()) {
                $available = $variantMapping->syncedPosItem
                    ? (int) floor($variantMapping->syncedPosItem->effective_availability)
                    : (int) $variantMapping->local_stock;
                if ($available < $newQuantity) {
                    return back()->with('error', "Cannot add more. Only {$available} units available in stock.");
                }
            }

            $cartItem->update([
                'quantity'                   => $newQuantity,
                'price'                      => $price,
                'product_variant_mapping_id' => $variantMapping?->id,
            ]);
        } else {
            $cart->items()->create([
                'product_id'                 => $product->id,
                'product_pos_unit_id'        => $selectedUnit?->id,
                'product_variant_mapping_id' => $variantMapping?->id,
                'unit_name'                  => $unitName,
                'quantity'                   => $request->quantity,
                'price'                      => $price,
                'variant'                    => $request->variant,
                'variant_key'                => $variantMapping?->variant_key ?? $request->variant_key ?? $request->variant,
                'variant_label'              => $variantMapping?->variant_label ?? $request->variant,
            ]);
        }

        return back()->with('success', 'Added to cart successfully.');
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $cart = $this->getCart($request);
        if (!$cart || $cartItem->cart_id !== $cart->id) {
            abort(403, 'Unauthorized cart action.');
        }

        $request->validate([
            'quantity' => 'required|integer|min:0'
        ]);

        if ($request->quantity < 1) {
            $cartItem->delete();
            return back()->with('success', 'Item removed from cart.');
        }

        $product = $cartItem->product;
        if ($product) {
            $multiplier = $cartItem->posUnit ? floatval($cartItem->posUnit->quantity_in_base_units ?: 1.0) : 1.0;
            $maxAllowed = (int) floor($product->stock / ($multiplier > 0 ? $multiplier : 1.0));
            if ($request->quantity > $maxAllowed) {
                $unitLabel = $cartItem->posUnit?->display_label ?? 'unit(s)';
                return back()->with('error', "Only {$maxAllowed} {$unitLabel} available in stock.");
            }
        }

        $cartItem->update(['quantity' => $request->quantity]);

        return back()->with('success', 'Cart updated.');
    }

    public function destroy(Request $request, CartItem $cartItem)
    {
        $cart = $this->getCart($request);
        if (!$cart || $cartItem->cart_id !== $cart->id) {
            abort(403, 'Unauthorized cart action.');
        }

        $cartItem->delete();
        return back()->with('success', 'Removed from cart.');
    }

    protected function getCart(Request $request)
    {
        $query = Cart::with(['items.product.posUnits', 'items.posUnit', 'items.variantMapping.syncedPosItem']);

        if (auth('customer')->check()) {
            return $query->where('customer_id', auth('customer')->id())->first();
        }
        
        $sessionId = $request->session()->getId();
        return $query->where('session_id', $sessionId)->first();
    }

    protected function getOrCreateCart(Request $request)
    {
        $cart = $this->getCart($request);
        
        if (!$cart) {
            $cart = Cart::create([
                'customer_id' => auth('customer')->id() ?? null,
                'session_id' => auth('customer')->check() ? null : $request->session()->getId()
            ]);
        }
        
        return $cart;
    }
}
