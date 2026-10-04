<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderTrackingController extends Controller
{
    /**
     * Show the public order tracking form.
     */
    public function index()
    {
        return Inertia::render('Frontend/OrderTracking/Index', [
            'order'      => null,
            'error'      => null,
            'settings'   => StoreSetting::allAsArray(),
        ]);
    }

    /**
     * Search for an order by tracking number (primary) or order ID (fallback).
     * No login required — open to everyone including guests.
     */
    public function track(Request $request)
    {
        $request->validate([
            'tracking_input' => 'required|string|max:50',
            'email'          => 'nullable|email',
        ]);

        $input = trim($request->tracking_input);

        // Try tracking number first (alphanumeric like SKY-XXXXXXXX),
        // then fall back to numeric order ID
        $query = Order::with('items.product');

        if (is_numeric($input)) {
            $query->where('id', (int) $input);
        } else {
            $query->where('tracking_number', strtoupper($input));
        }

        // If email provided, verify it matches — adds privacy/security for guests
        if ($request->email) {
            $email = $request->email;
            $query->where(function ($q) use ($email) {
                $q->whereHas('customer', fn ($c) => $c->where('email', $email))
                  ->orWhere('guest_email', $email)
                  ->orWhere('billing_email', $email);
            });
        }

        $order = $query->first();

        return Inertia::render('Frontend/OrderTracking/Index', [
            'order'         => $order,
            'error'         => $order ? null : 'No order found. Please check your tracking number or Order ID and try again.',
            'searched'      => $input,
            'settings'      => StoreSetting::allAsArray(),
        ]);
    }

    /**
     * Public shareable tracking URL: /track/{tracking_number}
     * Works for guests — no login needed.
     */
    public function trackDirect(string $tracking)
    {
        $order = Order::with('items.product')
            ->where('tracking_number', strtoupper($tracking))
            ->first();

        return Inertia::render('Frontend/OrderTracking/Index', [
            'order'    => $order,
            'error'    => $order ? null : 'Invalid or expired tracking link.',
            'searched' => strtoupper($tracking),
            'settings' => StoreSetting::allAsArray(),
        ]);
    }

    /**
     * Show logged-in customer's full order history.
     */
    public function myOrders()
    {
        $orders = Order::with('items.product')
            ->where('customer_id', auth('customer')->id())
            ->latest()
            ->paginate(10);

        return Inertia::render('Frontend/OrderTracking/MyOrders', [
            'orders' => $orders,
        ]);
    }

    /**
     * Show a single order detail for the authenticated customer.
     */
    public function myOrderDetail(Order $order)
    {
        if ($order->customer_id !== auth('customer')->id()) {
            abort(403, 'This order does not belong to your account.');
        }

        return Inertia::render('Frontend/OrderTracking/OrderDetail', [
            'order' => $order->load('items.product', 'customer'),
        ]);
    }
}
