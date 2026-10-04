<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StoreSetting;
use App\Services\InventoryService;
use App\Services\PosIntegration\PosReservationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    protected PosReservationService $reservationService;
    protected InventoryService $inventoryService;

    public function __construct(PosReservationService $reservationService, InventoryService $inventoryService)
    {
        $this->reservationService = $reservationService;
        $this->inventoryService = $inventoryService;
    }

    public function index(Request $request)
    {
        $query = Order::with('customer')->latest();

        // Search by tracking number or Order ID
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('tracking_number', 'like', "%{$search}%")
                  ->orWhere('inventory_reference', 'like', "%{$search}%")
                  ->orWhere('guest_email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($posStatus = $request->input('pos_reservation_status')) {
            $query->where('pos_reservation_status', $posStatus);
        }

        if ($startDate = $request->input('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate = $request->input('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return Inertia::render('Admin/Orders/Index', [
            'orders'  => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'pos_reservation_status', 'start_date', 'end_date'])
        ]);
    }

    public function show(Order $order)
    {
        return Inertia::render('Admin/Orders/Show', [
            'order' => $order->load('items.product', 'customer'),
        ]);
    }

    public function invoice(Order $order)
    {
        return Inertia::render('Admin/Orders/Invoice', [
            'order'    => $order->load('items.product', 'customer'),
            'settings' => StoreSetting::allAsArray(),
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,paid,processing,shipped,completed,cancelled'
        ]);

        $newStatus = $validated['status'];
        $oldStatus = $order->status;

        $order->update(['status' => $newStatus]);

        // When moving to processing, paid, shipped, or completed (e.g. COD order fulfilled)
        if (in_array($newStatus, ['paid', 'processing', 'shipped', 'completed'], true)) {
            if ($order->pos_reservation_status === 'reserved') {
                $this->reservationService->confirmReservation($order);
            }
        }

        // When cancelled, restore ecommerce inventory and release POS reservation (Section 140M)
        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
            $this->inventoryService->restoreOrderInventory($order);
        }

        return back()->with('success', 'Order status updated.');
    }
}
