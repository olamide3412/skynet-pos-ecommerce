<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
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
            'cart'   => $cart ? $cart->load(['items.product.posUnits', 'items.posUnit']) : null,
            'coupon' => $request->session()->get('applied_coupon'),
        ]);
    }

    public function store(Request $request)
    {
        if (!StoreSetting::getBool('shop_enabled', true)) {
            return back()->with('error', 'Online store shopping is temporarily closed. Please check back later.');
        }

        $request->validate([
            'product_id'          => 'required|exists:products,id',
            'quantity'            => 'required|integer|min:1',
            'product_pos_unit_id' => 'nullable|exists:product_pos_units,id',
            'variant'             => 'nullable|string',
            'variant_key'         => 'nullable|string',
        ]);

        $product = Product::with('posUnits')->findOrFail($request->product_id);

        if (!$product->isAvailableForPurchase()) {
            return back()->with('error', 'This product is currently unavailable for purchase.');
        }

        $cart = $this->getOrCreateCart($request);

        // Resolve selling unit
        $selectedUnit = null;
        if ($request->product_pos_unit_id) {
            $selectedUnit = $product->posUnits->firstWhere('id', $request->product_pos_unit_id);
            if (!$selectedUnit || !$selectedUnit->is_enabled_online) {
                return back()->with('error', 'The selected purchasing unit is not available.');
            }
        }

        $unitName = $selectedUnit ? $selectedUnit->display_label : ($product->pos_base_unit_name ?? 'Piece');
        $price = $product->getEffectivePriceForUnit($selectedUnit);

        // Apply distinct variant pricing override if provided
        if ($request->variant && $product->variants) {
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
            ->where('variant', $request->variant)
            ->first();

        if ($cartItem) {
            $cartItem->update([
                'quantity' => $cartItem->quantity + $request->quantity,
                'price'    => $price,
            ]);
        } else {
            $cart->items()->create([
                'product_id'          => $product->id,
                'product_pos_unit_id' => $selectedUnit?->id,
                'unit_name'           => $unitName,
                'quantity'            => $request->quantity,
                'price'               => $price,
                'variant'             => $request->variant,
                'variant_key'         => $request->variant_key ?? $request->variant,
                'variant_label'       => $request->variant,
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
        if ($product && $product->stock < $request->quantity) {
            return back()->with('error', "Only {$product->stock} unit(s) available in stock.");
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
        $query = Cart::with(['items.product.posUnits', 'items.posUnit']);

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
