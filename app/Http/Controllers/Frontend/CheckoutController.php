<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ShippingZone;
use App\Models\StoreSetting;
use App\Mail\AdminNewOrderAlertMail;
use App\Mail\CustomerOrderConfirmationMail;
use App\Services\ActivityLogger;
use App\Services\FlutterwaveService;
use App\Services\InventoryService;
use App\Services\PaystackService;
use App\Services\PosIntegration\PosReservationService;
use App\Services\PosIntegration\PosSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        if (!StoreSetting::getBool('shop_enabled', true)) {
            return redirect()->route('home')->with('warning', 'Online ordering is currently suspended. Please check back later.');
        }

        if (!StoreSetting::getBool('guest_checkout_enabled', true) && !auth('customer')->check()) {
            return redirect()->route('login')->with('warning', 'Please sign in or create an account to proceed to checkout.');
        }

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
            'cart'           => $cart->load(['items.product.posUnits', 'items.posUnit', 'items.variantMapping.syncedPosItem']),
            'settings'       => StoreSetting::allAsArray(),
            'coupon'         => $request->session()->get('applied_coupon'),
            'shipping_zones' => ShippingZone::where('is_active', true)->orderByDesc('is_default')->orderBy('state')->orderBy('city')->get(),
            'addresses'      => $customer ? $customer->addresses()->get() : [],
            'customer'       => $customer ? [
                'name'  => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone ?? '',
            ] : null,
        ]);
    }

    public function process(Request $request)
    {
        if (!StoreSetting::getBool('shop_enabled', true)) {
            return redirect()->route('home')->with('error', 'Online ordering is currently suspended.');
        }

        if (!StoreSetting::getBool('guest_checkout_enabled', true) && !auth('customer')->check()) {
            return redirect()->route('login')->with('warning', 'Please sign in or create an account to proceed to checkout.');
        }

        $settings = StoreSetting::allAsArray();

        // Build allowed gateway list based on admin settings
        $allowed = [];
        if (($settings['paystack_enabled'] ?? '0') === '1')      $allowed[] = 'paystack';
        if (($settings['flutterwave_enabled'] ?? '0') === '1')   $allowed[] = 'flutterwave';
        if (($settings['cod_enabled'] ?? '0') === '1') {
            $allowed[] = 'cod';
            $allowed[] = 'pod';
        }
        if (($settings['bank_transfer_enabled'] ?? '0') === '1') $allowed[] = 'bank_transfer';

        // ─── Bot Protection: Honeypot & Timing Check ─────────────────────
        if ($request->filled('b_hp_billing_company') || $request->filled('website_url')) {
            Log::info("Checkout bot trapped via honeypot from IP {$request->ip()}");
            return redirect()->route('cart.index')->with('error', 'Unable to complete your order at this time. Please contact support.');
        }

        $renderedAt = $request->input('_rendered_at');
        if ($renderedAt && is_numeric($renderedAt)) {
            $elapsedSeconds = (microtime(true) * 1000 - (float) $renderedAt) / 1000;
            if ($elapsedSeconds > 0 && $elapsedSeconds < 1.5) {
                Log::info("Checkout bot trapped via speed detection ({$elapsedSeconds}s) from IP {$request->ip()}");
                return redirect()->route('cart.index')->with('error', 'Please take your time to review checkout details before placing your order.');
            }
        }

        $request->validate([
            'email'            => 'required|email',
            'name'             => 'required|string',
            'phone'            => 'nullable|string',
            'address'          => 'nullable|string',
            'delivery_method'  => 'nullable|in:delivery,pickup',
            'shipping_zone_id' => 'nullable|exists:shipping_zones,id',
            'gateway'          => 'required|in:' . implode(',', $allowed ?: ['paystack']),
        ]);

        $gateway = $request->gateway;

        // ─── Excessive Pending Pay on Delivery (POD) Protection ───────────
        if (in_array($gateway, ['pod', 'cod'], true)) {
            $email = strtolower(trim($request->email));
            $phone = preg_replace('/[^0-9]/', '', (string) $request->phone);

            $pendingPodQuery = Order::where(function ($q) {
                $q->where('payment_method', 'pod')
                  ->orWhere('payment_method', 'cod');
            })
            ->whereNotIn('pod_status', ['cash_collected', 'remitted_to_store', 'reconciled'])
            ->whereNotIn('status', ['completed', 'cancelled', 'refunded'])
            ->where(function ($q) use ($email, $phone, $request) {
                $q->where('billing_email', $email);
                if (!empty($phone) && strlen($phone) >= 7) {
                    $q->orWhere('billing_phone', 'like', "%{$phone}%");
                }
                if ($customerId = auth('customer')->id()) {
                    $q->orWhere('customer_id', $customerId);
                }
            });

            if ($pendingPodQuery->count() >= 3) {
                return redirect()->route('cart.index')->with(
                    'warning',
                    'You currently have 3 pending Pay on Delivery orders awaiting fulfillment. Please wait for delivery or pay online to place additional orders.'
                );
            }
        }

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

        // Apply coupon if stored in session
        $appliedCoupon = $request->session()->get('applied_coupon');
        $discountAmount = 0.0;
        $couponCode = null;

        if ($appliedCoupon) {
            $coupon = Coupon::find($appliedCoupon['id'] ?? null);
            if ($coupon && $coupon->isValidForAmount($subtotal)) {
                $discountAmount = (float) $coupon->calculateDiscount($subtotal, $cart);
                $couponCode = $coupon->code;
                $coupon->increment('used_count');
            }
        }

        $taxableSubtotal = max(0, $subtotal - $discountAmount);
        $tax = 0;
        if (($settings['tax_enabled'] ?? '0') === '1') {
            $tax = ($taxableSubtotal * floatval($settings['tax_rate'] ?? 0)) / 100;
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
                if ($request->shipping_zone_id) {
                    $zone = ShippingZone::where('is_active', true)->find($request->shipping_zone_id);
                    $shippingFee = $zone ? floatval($zone->rate) : floatval($settings['waybill_fee'] ?? 0);
                } else {
                    $shippingFee = floatval($settings['waybill_fee'] ?? 0);
                }
            }
        }

        $totalAmount = max(0, $subtotal - $discountAmount + $tax + $serviceFee + $shippingFee);

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
            'subtotal'                    => $subtotal,
            'total_amount'                => $totalAmount,
            'discount_amount'             => $discountAmount,
            'coupon_code'                 => $couponCode,
            'shipping_fee'                => $shippingFee,
            'tax_amount'                  => $tax,
            'service_fee'                 => $serviceFee,
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
            $variantMapping = $item->variantMapping;

            $invSource = $product?->inventory_source ?? 'ecommerce';
            $posItemId = $product?->pos_item_id;
            $posItemUnitId = $posUnit?->pos_item_unit_id;
            $unitName = $item->unit_name ?: ($posUnit?->display_label ?? $product?->pos_base_unit_name ?? 'Piece');
            $qtyInBase = $posUnit ? floatval($posUnit->quantity_in_base_units * $item->quantity) : floatval($item->quantity);
            $standardSnapshot = $posUnit ? $posUnit->synced_standard_price : ($product?->price ?? $item->price);
            $unitMultiplier = $posUnit ? floatval($posUnit->quantity_in_base_units) : 1.0;
            $unitCostPrice = floatval($product?->cost_price ?? 0) * $unitMultiplier;

            // If line is bound to a POS-controlled variant mapping:
            if ($variantMapping && $variantMapping->isPosControlled()) {
                $invSource = 'pos';
                $posItemId = $variantMapping->pos_item_id;
                $unitCostPrice = floatval($variantMapping->syncedPosItem?->buying_price ?? $product?->cost_price ?? 0);
                $standardSnapshot = $variantMapping->syncedPosItem?->standard_price ?? $item->price;
                $unitName = $variantMapping->syncedPosItem?->base_unit_name ?? $unitName;
            }

            $order->items()->create([
                'product_id'                  => $item->product_id,
                'product_variant_mapping_id'  => $item->product_variant_mapping_id,
                'quantity'                    => $item->quantity,
                'price'                       => $item->price,
                'cost_price'                  => $unitCostPrice,
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

        // ─── Referral Attribution & Pending Commission ─────────────────
        $refCode = $request->cookie(\App\Services\Referral\ReferralTrackingService::COOKIE_NAME);
        if ($refCode) {
            app(\App\Services\Referral\AffiliateCommissionService::class)->createPending($order, $refCode);
        }

        // ─── Payment Gateway Routing ──────────────────────────────────
        if ($request->gateway === 'cod' || $request->gateway === 'pod') {
            // For POD orders, clear cart immediately upon placement
            $cart->items()->delete();
            $cart->delete();
            $request->session()->forget('applied_coupon');

            $order->update([
                'payment_method' => 'pod',
                'status'         => 'processing',
                'pod_status'     => 'pending_dispatch',
            ]);

            $order->recordStatusHistory(
                toStatus: 'processing',
                fromStatus: null,
                event: 'order_placed',
                comment: 'Order placed by customer with Payment on Delivery (POD).'
            );

            Payment::create([
                'order_id'  => $order->id,
                'provider'  => 'pod',
                'reference' => 'pod_' . $order->id . '_' . uniqid(),
                'amount'    => $order->total_amount,
                'status'    => 'pending',
                'response'  => ['gateway' => 'payment_on_delivery'],
            ]);

            // Confirm POS reservation upon placing POD order
            $this->reservationService->confirmReservation($order);
            $this->sendOrderNotifications($order);

            return redirect()->route('checkout.success', $order->tracking_number)
                ->with('success', 'Order placed successfully! You will pay on delivery.');
        }

        if ($request->gateway === 'bank_transfer') {
            $cart->items()->delete();
            $cart->delete();
            $request->session()->forget('applied_coupon');

            $order->update([
                'payment_method' => 'bank_transfer',
                'status'         => 'pending',
            ]);

            Payment::create([
                'order_id'  => $order->id,
                'provider'  => 'bank_transfer',
                'reference' => 'bt_' . $order->id . '_' . uniqid(),
                'amount'    => $order->total_amount,
                'status'    => 'pending',
                'response'  => ['gateway' => 'bank_transfer'],
            ]);

            $this->sendOrderNotifications($order);

            return redirect()->route('checkout.success', $order->tracking_number)
                ->with('success', 'Order placed successfully! Please complete your direct bank transfer.');
        }

        if ($request->gateway === 'paystack') {
            $paystack = new PaystackService();
            $response = $paystack->initializePayment($order, $request->email);
            if (!empty($response['status']) && !empty($response['data']['authorization_url'])) {
                $ref = $response['data']['reference'];
                $order->update([
                    'payment_method'    => 'paystack',
                    'payment_reference' => $ref,
                ]);

                Payment::updateOrCreate(
                    ['reference' => $ref],
                    [
                        'order_id' => $order->id,
                        'provider' => 'paystack',
                        'amount'   => $order->total_amount,
                        'status'   => 'pending',
                        'response' => $response,
                    ]
                );

                return Inertia::location($response['data']['authorization_url']);
            }
        } elseif ($request->gateway === 'flutterwave') {
            $flutterwave = new FlutterwaveService();
            $response = $flutterwave->initializePayment($order, $request->email, $request->name);
            if (!empty($response['status']) && $response['status'] === 'success' && !empty($response['data']['link'])) {
                $txRef = $response['data']['tx_ref'] ?? ('fw_' . uniqid() . '_' . $order->id);
                $order->update([
                    'payment_method'    => 'flutterwave',
                    'payment_reference' => $txRef,
                ]);

                Payment::updateOrCreate(
                    ['reference' => $txRef],
                    [
                        'order_id' => $order->id,
                        'provider' => 'flutterwave',
                        'amount'   => $order->total_amount,
                        'status'   => 'pending',
                        'response' => $response,
                    ]
                );

                return Inertia::location($response['data']['link']);
            }
        }

        // If gateway initialization failed, release the POS reservation and restore local stock
        $this->reservationService->releaseReservation($order);
        $this->inventoryService->restoreOrderInventory($order);
        $order->update(['status' => 'cancelled']);

        return back()->with('error', 'Payment initialization failed. Please try again.');
    }

    public function callback(Request $request)
    {
        $reference = $request->input('reference') ?? $request->input('trxref') ?? $request->input('tx_ref');
        $transactionId = $request->input('transaction_id');
        $provider = $request->input('provider');
        $statusParam = strtolower((string) ($request->input('status') ?? ''));

        // If provider was not passed in URL, infer from reference prefix
        if (!$provider && $reference) {
            if (str_starts_with($reference, 'ps_')) {
                $provider = 'paystack';
            } elseif (str_starts_with($reference, 'fw_')) {
                $provider = 'flutterwave';
            }
        }

        if (!$reference) {
            return redirect()->route('cart.index')->with('error', 'No payment reference was received.');
        }

        $order = Order::where('payment_reference', $reference)->first();

        // If not found by reference, try finding by payment record or ID
        if (!$order && $transactionId) {
            $payment = Payment::where('reference', $transactionId)->first();
            if ($payment) {
                $order = $payment->order;
            }
        }

        if (!$order) {
            return redirect()->route('cart.index')->with('error', 'Unable to find an order matching this payment reference.');
        }

        // Check if user explicitly cancelled on gateway
        if (in_array($statusParam, ['cancelled', 'failed', 'declined'], true)) {
            if ($order->status === 'pending') {
                $this->reservationService->releaseReservation($order);
                $this->inventoryService->restoreOrderInventory($order);
                $order->update(['status' => 'cancelled']);

                Payment::where('order_id', $order->id)->where('status', 'pending')->update(['status' => 'cancelled']);
            }

            return redirect()->route('checkout.index')->with('error', 'Payment was cancelled or not completed. Please try again.');
        }

        // If the order has already been verified and processed (e.g. via asynchronous webhook)
        if ($order->status !== 'pending') {
            return redirect()->route('checkout.success', $order->tracking_number)
                ->with('success', 'Payment confirmed! Please keep your tracking number.');
        }

        // Authoritative verification against payment gateway API
        $verified = false;
        $verificationData = [];

        if ($provider === 'flutterwave' || str_starts_with($reference, 'fw_') || $transactionId) {
            $flutterwave = new FlutterwaveService();
            $verifyId = $transactionId ?: $reference;
            $verify = $flutterwave->verifyPayment($verifyId);
            $verificationData = $verify;

            if (
                is_array($verify) &&
                ($verify['status'] ?? '') === 'success' &&
                isset($verify['data']['status']) &&
                $verify['data']['status'] === 'successful' &&
                ($verify['data']['currency'] ?? '') === 'NGN' &&
                floatval($verify['data']['amount'] ?? 0) >= floatval($order->total_amount)
            ) {
                $verified = true;
            }
        } else {
            // Default to Paystack
            $paystack = new PaystackService();
            $verify = $paystack->verifyPayment($reference);
            $verificationData = $verify;

            if (
                is_array($verify) &&
                ($verify['status'] ?? false) === true &&
                isset($verify['data']['status']) &&
                $verify['data']['status'] === 'success' &&
                floatval(($verify['data']['amount'] ?? 0) / 100) >= floatval($order->total_amount)
            ) {
                $verified = true;
            }
        }

        if ($verified) {
            $order->update([
                'status'         => 'processing',
                'payment_method' => $provider ?: $order->payment_method,
            ]);

            Payment::updateOrCreate(
                ['reference' => $reference],
                [
                    'order_id' => $order->id,
                    'provider' => $provider ?: 'paystack',
                    'amount'   => $order->total_amount,
                    'status'   => 'completed',
                    'response' => $verificationData,
                ]
            );

            // Confirm POS reservation upon verified payment
            $this->reservationService->confirmReservation($order);

            // Clear active cart upon confirmed payment (Section 140BZ)
            if ($order->customer_id) {
                $userCart = Cart::where('customer_id', $order->customer_id)->first();
                if ($userCart) {
                    $userCart->items()->delete();
                    $userCart->delete();
                }
            }
            $sessionCart = Cart::where('session_id', $request->session()->getId())->first();
            if ($sessionCart) {
                $sessionCart->items()->delete();
                $sessionCart->delete();
            }
            $request->session()->forget('applied_coupon');

            $this->sendOrderNotifications($order);

            Log::info("Payment verified successfully via callback for Order #{$order->id} ({$reference})");

            return redirect()->route('checkout.success', $order->tracking_number)
                ->with('success', 'Payment confirmed! Please keep your tracking number.');
        }

        // If verification failed
        Log::warning("Payment verification failed for Order #{$order->id} ({$reference})", [
            'response' => $verificationData,
        ]);

        $this->reservationService->releaseReservation($order);
        $this->inventoryService->restoreOrderInventory($order);
        $order->update(['status' => 'cancelled']);

        Payment::updateOrCreate(
            ['reference' => $reference],
            [
                'order_id' => $order->id,
                'provider' => $provider ?: 'unknown',
                'amount'   => $order->total_amount,
                'status'   => 'failed',
                'response' => $verificationData,
            ]
        );

        return redirect()->route('checkout.index')
            ->with('error', 'Payment verification failed or was declined. Your reserved stock has been released.');
    }

    public function success($tracking)
    {
        $order = Order::with('items.product')->where('tracking_number', $tracking)->firstOrFail();

        $bankInfo = null;
        if ($order->payment_method === 'bank_transfer') {
            $bankInfo = [
                'bank_name'           => StoreSetting::get('bank_name', 'First Bank of Nigeria'),
                'bank_account_number' => StoreSetting::get('bank_account_number', '0123456789'),
                'bank_account_name'   => StoreSetting::get('bank_account_name', 'Skynet POS & E-Commerce Ltd'),
                'bank_instructions'   => StoreSetting::get('bank_instructions', 'Please transfer total amount using your Tracking Number as reference/narration, then contact support.'),
            ];
        }

        return Inertia::render('Frontend/Checkout/Success', [
            'order'     => $order,
            'bank_info' => $bankInfo,
        ]);
    }

    public function paystackWebhook(Request $request)
    {
        $secretKey = config('services.paystack.secret_key');
        
        if ($request->header('x-paystack-signature') !== hash_hmac('sha512', $request->getContent(), $secretKey)) {
            Log::warning('Invalid Paystack Webhook Signature', $request->all());
            ActivityLogger::webhook("Rejected Paystack webhook: Invalid signature", [
                'headers' => [
                    'x-paystack-signature' => $request->header('x-paystack-signature'),
                ],
                'payload' => $request->all(),
            ], 'Paystack Webhook');
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        if ($event === 'charge.success') {
            $reference = $data['reference'] ?? null;
            if ($reference) {
                $order = Order::where('payment_reference', $reference)->first();

                if ($order && $order->status === 'pending') {
                    if (($data['amount'] ?? 0) >= ($order->total_amount * 100)) {
                        $order->update([
                            'status'         => 'processing',
                            'payment_method' => 'paystack',
                        ]);

                        Payment::updateOrCreate(
                            ['reference' => $reference],
                            [
                                'order_id' => $order->id,
                                'provider' => 'paystack',
                                'amount'   => $order->total_amount,
                                'status'   => 'completed',
                                'response' => $data,
                            ]
                        );
                        
                        // Authoritatively confirm POS reservation
                        $this->reservationService->confirmReservation($order);
                        $this->sendOrderNotifications($order);

                        if ($order->customer_id) {
                            $userCart = Cart::where('customer_id', $order->customer_id)->first();
                            if ($userCart) {
                                $userCart->items()->delete();
                                $userCart->delete();
                            }
                        }

                        Log::info("Paystack webhook: payment successful and POS reservation confirmed for Order #{$order->id}");

                        ActivityLogger::webhook("Paystack webhook: Verified payment of ₦" . number_format($order->total_amount, 2) . " for Order #{$order->tracking_number} (Ref: {$reference})", [
                            'event'           => $event,
                            'reference'       => $reference,
                            'order_id'        => $order->id,
                            'tracking_number' => $order->tracking_number,
                            'amount_paid'     => ($data['amount'] ?? 0) / 100,
                            'order_total'     => (float) $order->total_amount,
                            'channel'         => $data['channel'] ?? null,
                            'customer_email'  => $data['customer']['email'] ?? $order->customer_email,
                            'customer_name'   => $order->customer_name,
                        ], 'Paystack Webhook');
                    } else {
                        ActivityLogger::webhook("Paystack webhook: Payment amount mismatch for Order #{$order->tracking_number} (Paid: ₦" . number_format(($data['amount'] ?? 0) / 100, 2) . ", Expected: ₦" . number_format($order->total_amount, 2) . ")", [
                            'event'           => $event,
                            'reference'       => $reference,
                            'order_id'        => $order->id,
                            'tracking_number' => $order->tracking_number,
                            'amount_paid'     => ($data['amount'] ?? 0) / 100,
                            'order_total'     => (float) $order->total_amount,
                        ], 'Paystack Webhook');
                    }
                } elseif ($order) {
                    ActivityLogger::webhook("Paystack webhook: Received 'charge.success' for Order #{$order->tracking_number} (Ref: {$reference}), but order status is already '{$order->status}'", [
                        'event'           => $event,
                        'reference'       => $reference,
                        'order_id'        => $order->id,
                        'tracking_number' => $order->tracking_number,
                        'order_status'    => $order->status,
                    ], 'Paystack Webhook');
                } else {
                    ActivityLogger::webhook("Paystack webhook: Received 'charge.success' (Ref: {$reference}), but no matching order was found", [
                        'event'     => $event,
                        'reference' => $reference,
                        'amount'    => ($data['amount'] ?? 0) / 100,
                    ], 'Paystack Webhook');
                }
            }
        } else {
            ActivityLogger::webhook("Paystack webhook received event '{$event}'", [
                'event'   => $event,
                'payload' => $data,
            ], 'Paystack Webhook');
        }

        return response()->json(['status' => 'success']);
    }

    public function flutterwaveWebhook(Request $request)
    {
        $secretHash = config('services.flutterwave.secret_hash');
        $signature = $request->header('verif-hash');

        if (!$signature || $signature !== $secretHash) {
            Log::warning('Invalid Flutterwave Webhook Signature');
            ActivityLogger::webhook("Rejected Flutterwave webhook: Invalid signature", [
                'received_hash' => $signature,
                'payload'       => $request->all(),
            ], 'Flutterwave Webhook');
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        if ($event === 'charge.completed' && isset($data['status']) && $data['status'] === 'successful') {
            $txRef = $data['tx_ref'] ?? null;
            if ($txRef) {
                $order = Order::where('payment_reference', $txRef)->first();

                if ($order && $order->status === 'pending') {
                    if (($data['amount'] ?? 0) >= $order->total_amount) {
                        $order->update([
                            'status'         => 'processing',
                            'payment_method' => 'flutterwave',
                        ]);

                        Payment::updateOrCreate(
                            ['reference' => $txRef],
                            [
                                'order_id' => $order->id,
                                'provider' => 'flutterwave',
                                'amount'   => $order->total_amount,
                                'status'   => 'completed',
                                'response' => $data,
                            ]
                        );
                        
                        // Authoritatively confirm POS reservation
                        $this->reservationService->confirmReservation($order);
                        $this->sendOrderNotifications($order);

                        if ($order->customer_id) {
                            $userCart = Cart::where('customer_id', $order->customer_id)->first();
                            if ($userCart) {
                                $userCart->items()->delete();
                                $userCart->delete();
                            }
                        }

                        Log::info("Flutterwave webhook: payment successful and POS reservation confirmed for Order #{$order->id}");

                        ActivityLogger::webhook("Flutterwave webhook: Verified payment of ₦" . number_format($order->total_amount, 2) . " for Order #{$order->tracking_number} (tx_ref: {$txRef})", [
                            'event'           => $event,
                            'tx_ref'          => $txRef,
                            'flw_ref'         => $data['flw_ref'] ?? null,
                            'order_id'        => $order->id,
                            'tracking_number' => $order->tracking_number,
                            'amount_paid'     => $data['amount'] ?? 0,
                            'order_total'     => (float) $order->total_amount,
                            'customer_email'  => $data['customer']['email'] ?? $order->customer_email,
                            'customer_name'   => $order->customer_name,
                        ], 'Flutterwave Webhook');
                    } else {
                        ActivityLogger::webhook("Flutterwave webhook: Payment amount mismatch for Order #{$order->tracking_number} (Paid: ₦" . number_format($data['amount'] ?? 0, 2) . ", Expected: ₦" . number_format($order->total_amount, 2) . ")", [
                            'event'           => $event,
                            'tx_ref'          => $txRef,
                            'order_id'        => $order->id,
                            'tracking_number' => $order->tracking_number,
                            'amount_paid'     => $data['amount'] ?? 0,
                            'order_total'     => (float) $order->total_amount,
                        ], 'Flutterwave Webhook');
                    }
                } elseif ($order) {
                    ActivityLogger::webhook("Flutterwave webhook: Received 'charge.completed' for Order #{$order->tracking_number} (tx_ref: {$txRef}), but order status is already '{$order->status}'", [
                        'event'           => $event,
                        'tx_ref'          => $txRef,
                        'order_id'        => $order->id,
                        'tracking_number' => $order->tracking_number,
                        'order_status'    => $order->status,
                    ], 'Flutterwave Webhook');
                } else {
                    ActivityLogger::webhook("Flutterwave webhook: Received 'charge.completed' (tx_ref: {$txRef}), but no matching order was found", [
                        'event'   => $event,
                        'tx_ref'  => $txRef,
                        'amount'  => $data['amount'] ?? 0,
                    ], 'Flutterwave Webhook');
                }
            }
        } else {
            ActivityLogger::webhook("Flutterwave webhook received event '{$event}'", [
                'event'   => $event,
                'payload' => $data,
            ], 'Flutterwave Webhook');
        }

        return response()->json(['status' => 'success']);
    }

    protected function sendOrderNotifications(Order $order): void
    {
        try {
            \App\Jobs\SendOrderConfirmationMailJob::dispatch($order);
        } catch (\Throwable $e) {
            Log::error("Failed dispatching order notification mail job for Order #{$order->id}: " . $e->getMessage());
        }
    }

    protected function getCart(Request $request)
    {
        $query = Cart::with(['items.product.posUnits', 'items.posUnit', 'items.variantMapping.syncedPosItem']);

        if (auth('customer')->check()) {
            return $query->where('customer_id', auth('customer')->id())->first();
        }
        return $query->where('session_id', $request->session()->getId())->first();
    }
}
