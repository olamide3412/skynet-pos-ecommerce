<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\StoreSetting;
use App\Services\FlutterwaveService;
use App\Services\InventoryService;
use App\Services\PaystackService;
use App\Services\PosIntegration\PosReservationService;
use App\Services\PosIntegration\PosSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    protected PosReservationService $reservationService;
    protected PosSyncService $syncService;
    protected InventoryService $inventoryService;

    public function __construct(
        PosReservationService $reservationService,
        PosSyncService $syncService,
        InventoryService $inventoryService
    ) {
        $this->reservationService = $reservationService;
        $this->syncService = $syncService;
        $this->inventoryService = $inventoryService;
    }

    public function index(Request $request)
    {
        $cart = $this->getCart($request);

        if (!$cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        // Revalidate cart prices and availability (Section 140BY)
        $revalidation = $this->syncService->revalidateCart($cart);
        if (!$revalidation['valid']) {
            $msg = implode(' ', $revalidation['messages']);
            return redirect()->route('cart.index')->with('warning', $msg ?: 'Your cart has updated with latest availability/pricing. Please review.');
        }

        $customer = auth('customer')->user();

        return Inertia::render('Frontend/Checkout/Index', [
            'cart'     => $cart->load(['items.product.posUnits', 'items.posUnit']),
            'settings' => StoreSetting::allAsArray(),
            'customer' => $customer ? [
                'name'  => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone ?? '',
            ] : null,
        ]);
    }

    public function process(Request $request)
    {
        $settings = StoreSetting::allAsArray();

        // Build allowed gateway list based on admin settings
        $allowed = [];
        if (($settings['paystack_enabled'] ?? '0') === '1')    $allowed[] = 'paystack';
        if (($settings['flutterwave_enabled'] ?? '0') === '1') $allowed[] = 'flutterwave';
        if (($settings['cod_enabled'] ?? '0') === '1')         $allowed[] = 'cod';

        $request->validate([
            'email'           => 'required|email',
            'name'            => 'required|string',
            'phone'           => 'nullable|string',
            'address'         => 'nullable|string',
            'delivery_method' => 'nullable|in:delivery,pickup',
            'gateway'         => 'required|in:' . implode(',', $allowed ?: ['paystack']),
        ]);

        $cart = $this->getCart($request);

        if (!$cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Cart is empty.');
        }

        // Revalidate prices and stock before proceeding
        $revalidation = $this->syncService->revalidateCart($cart);
        if (!$revalidation['valid']) {
            $msg = implode(' ', $revalidation['messages']);
            return redirect()->route('cart.index')->with('warning', $msg ?: 'Your cart was refreshed with latest prices. Please review and continue.');
        }

        // ─── Calculate totals ─────────────────────────────────────────
        $subtotal = $cart->items->sum(fn ($item) => $item->price * $item->quantity);

        $tax = 0;
        if (($settings['tax_enabled'] ?? '0') === '1') {
            $tax = ($subtotal * floatval($settings['tax_rate'] ?? 0)) / 100;
        }

        $serviceFee = 0;
        if (($settings['service_fee_enabled'] ?? '0') === '1') {
            $serviceFee = floatval($settings['service_fee_amount'] ?? 0);
        }

        $shippingFee = 0;
        $deliveryMethod = $request->delivery_method ?? 'delivery';
        if ($deliveryMethod === 'delivery' && ($settings['shipping_enabled'] ?? '0') === '1') {
            $freeThreshold = floatval($settings['free_shipping_threshold'] ?? 0);
            if ($freeThreshold === 0.0 || $subtotal < $freeThreshold) {
                $shippingFee = floatval($settings['waybill_fee'] ?? 0);
            }
        }

        $totalAmount = $subtotal + $tax + $serviceFee + $shippingFee;

        // ─── POS Reservation for POS-linked lines (Section 140BY & Section 50) ───
        $reservationResult = $this->reservationService->reserveForCart($cart);
        if (!$reservationResult['status']) {
            return back()->with('error', $reservationResult['message'] ?? 'Stock reservation could not be secured.');
        }

        $inventoryRef = $reservationResult['inventory_reference'];
        $posStatus = ($reservationResult['has_pos_items'] ?? false) ? 'reserved' : 'none';

        // ─── Create Order ─────────────────────────────────────────────
        $order = Order::create([
            'customer_id'                 => auth('customer')->id() ?? null,
            'guest_email'                 => auth('customer')->check() ? null : $request->email,
            'total_amount'                => $totalAmount,
            'status'                      => 'pending',
            'payment_method'              => $request->gateway,
            'billing_name'                => $request->name,
            'billing_email'               => $request->email,
            'billing_phone'               => $request->phone,
            'billing_address'             => $request->address,
            'delivery_method'             => $deliveryMethod,
            'inventory_reference'         => $inventoryRef,
            'pos_reservation_status'      => $posStatus,
            'pos_reservation_expires_at'  => $posStatus === 'reserved' ? now()->addMinutes(30) : null,
        ]);

        foreach ($cart->items as $item) {
            $product = $item->product;
            $posUnit = $item->posUnit;

            $invSource = $product?->inventory_source ?? 'ecommerce';
            $posItemId = $product?->pos_item_id;
            $posItemUnitId = $posUnit?->pos_item_unit_id;
            $unitName = $item->unit_name ?: ($posUnit?->display_label ?? $product?->pos_base_unit_name ?? 'Piece');
            $qtyInBase = $posUnit ? floatval($posUnit->quantity_in_base_units * $item->quantity) : floatval($item->quantity);
            $standardSnapshot = $posUnit ? $posUnit->synced_standard_price : ($product?->price ?? $item->price);

            $order->items()->create([
                'product_id'                  => $item->product_id,
                'quantity'                    => $item->quantity,
                'price'                       => $item->price,
                'variant'                     => $item->variant,
                'inventory_source'            => $invSource,
                'pos_item_id'                 => $posItemId,
                'pos_item_unit_id'            => $posItemUnitId,
                'unit_name'                   => $unitName,
                'quantity_in_base_units'      => $qtyInBase,
                'standard_pos_price_snapshot' => $standardSnapshot,
                'online_unit_price'           => $item->price,
                'variant_key'                 => $item->variant_key,
                'variant_label'               => $item->variant_label,
            ]);
        }

        // Deduct local stock for ecommerce-only items (Section 140L)
        $this->inventoryService->deductOrderInventory($order);

        // Clear the cart
        $cart->items()->delete();
        $cart->delete();

        // ─── Payment Gateway Routing ──────────────────────────────────
        if ($request->gateway === 'cod') {
            return redirect()->route('checkout.success', $order->tracking_number)
                ->with('success', 'Order placed successfully! You will pay on delivery.');
        }

        if ($request->gateway === 'paystack') {
            $paystack = new PaystackService();
            $response = $paystack->initializePayment($order, $request->email);
            if ($response['status']) {
                $order->update(['payment_reference' => $response['data']['reference']]);
                return Inertia::location($response['data']['authorization_url']);
            }
        } elseif ($request->gateway === 'flutterwave') {
            $flutterwave = new FlutterwaveService();
            $response = $flutterwave->initializePayment($order, $request->email, $request->name);
            if ($response['status'] === 'success') {
                $order->update(['payment_reference' => $response['data']['tx_ref']]);
                return Inertia::location($response['data']['link']);
            }
        }

        // If gateway initialization failed, release the POS reservation
        $this->reservationService->releaseReservation($order);
        $this->inventoryService->restoreOrderInventory($order);

        return back()->with('error', 'Payment initialization failed. Please try again.');
    }

    public function callback(Request $request)
    {
        $reference = $request->input('reference') ?? $request->input('tx_ref');
        
        if ($reference) {
            $order = Order::where('payment_reference', $reference)->first();
            if ($order) {
                // Confirm POS reservation upon successful return if not already confirmed
                $this->reservationService->confirmReservation($order);

                return redirect()->route('checkout.success', $order->tracking_number)
                    ->with('success', 'Payment confirmed! Please keep your tracking number.');
            }
        }

        return redirect()->route('customer.orders')->with('success', 'Payment processing. You can check order status here.');
    }

    public function success($tracking)
    {
        $order = Order::with('items.product')->where('tracking_number', $tracking)->firstOrFail();

        return Inertia::render('Frontend/Checkout/Success', [
            'order' => $order,
        ]);
    }

    public function paystackWebhook(Request $request)
    {
        $secretKey = config('services.paystack.secret_key');
        
        if ($request->header('x-paystack-signature') !== hash_hmac('sha512', $request->getContent(), $secretKey)) {
            Log::warning('Invalid Paystack Webhook Signature', $request->all());
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        if ($event === 'charge.success') {
            $reference = $data['reference'];
            $order = Order::where('payment_reference', $reference)->first();

            if ($order && $order->status === 'pending') {
                if ($data['amount'] >= ($order->total_amount * 100)) {
                    $order->update(['status' => 'processing']);
                    
                    // Authoritatively confirm POS reservation (Section 52)
                    $this->reservationService->confirmReservation($order);

                    Log::info("Paystack payment successful and POS reservation confirmed for Order #{$order->id}");
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    public function flutterwaveWebhook(Request $request)
    {
        $secretHash = config('services.flutterwave.secret_hash');
        $signature = $request->header('verif-hash');

        if (!$signature || $signature !== $secretHash) {
            Log::warning('Invalid Flutterwave Webhook Signature');
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        if ($event === 'charge.completed' && isset($data['status']) && $data['status'] === 'successful') {
            $txRef = $data['tx_ref'];
            $order = Order::where('payment_reference', $txRef)->first();

            if ($order && $order->status === 'pending') {
                if ($data['amount'] >= $order->total_amount) {
                    $order->update(['status' => 'processing']);
                    
                    // Authoritatively confirm POS reservation (Section 53)
                    $this->reservationService->confirmReservation($order);

                    Log::info("Flutterwave payment successful and POS reservation confirmed for Order #{$order->id}");
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    protected function getCart(Request $request)
    {
        $query = Cart::with(['items.product.posUnits', 'items.posUnit']);

        if (auth('customer')->check()) {
            return $query->where('customer_id', auth('customer')->id())->first();
        }
        return $query->where('session_id', $request->session()->getId())->first();
    }
}
