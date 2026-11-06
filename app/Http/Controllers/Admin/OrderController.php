<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StoreSetting;
use App\Services\ActivityLogger;
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
        $currentUser = auth('web')->user();
        $query = Order::with('customer')->latest();

        $canViewAll = $currentUser->isSuperAdmin()
            || in_array($currentUser->role, ['administrator', 'super administrator'], true)
            || $currentUser->can('orders.view_all');

        // If user cannot view all orders (e.g., dedicated Rider / Dispatch staff), restrict to their assigned deliveries
        if (!$canViewAll) {
            $query->where('assigned_staff_id', $currentUser->id);
        }

        // Search by tracking number, Order ID, guest email, or coupon code
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('tracking_number', 'like', "%{$search}%")
                  ->orWhere('inventory_reference', 'like', "%{$search}%")
                  ->orWhere('coupon_code', 'like', "%{$search}%")
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

        $podCashInHand = (float) Order::where(function ($q) use ($currentUser) {
                $q->where('assigned_staff_id', $currentUser->id)
                  ->orWhere('remitted_by_staff_id', $currentUser->id);
            })
            ->where('payment_method', 'pod')
            ->where('pod_status', 'cash_collected')
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(pod_amount_collected, total_amount)'));

        $podCashInHandCount = Order::where(function ($q) use ($currentUser) {
                $q->where('assigned_staff_id', $currentUser->id)
                  ->orWhere('remitted_by_staff_id', $currentUser->id);
            })
            ->where('payment_method', 'pod')
            ->where('pod_status', 'cash_collected')
            ->count();

        // Status counts for Manage Orders quick tabs / circular status badges
        $baseCountsQuery = Order::query();
        if (!$canViewAll) {
            $baseCountsQuery->where('assigned_staff_id', $currentUser->id);
        }

        $rawCounts = (clone $baseCountsQuery)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $statusCounts = [
            'all'        => (int) array_sum($rawCounts),
            'pending'    => (int) ($rawCounts['pending'] ?? 0),
            'processing' => (int) ($rawCounts['processing'] ?? 0),
            'paid'       => (int) ($rawCounts['paid'] ?? 0),
            'shipped'    => (int) ($rawCounts['shipped'] ?? 0),
            'completed'  => (int) ($rawCounts['completed'] ?? 0),
            'cancelled'  => (int) ($rawCounts['cancelled'] ?? 0),
        ];

        return Inertia::render('Admin/Orders/Index', [
            'orders'       => $query->paginate(15)->withQueryString(),
            'podCustody'   => [
                'cash_in_hand' => $podCashInHand,
                'count'        => $podCashInHandCount,
            ],
            'statusCounts' => $statusCounts,
            'filters'      => $request->only(['search', 'status', 'pos_reservation_status', 'start_date', 'end_date'])
        ]);
    }

    public function show(Order $order)
    {
        $currentUser = auth('web')->user();
        $canReconcile = $currentUser && ($currentUser->isSuperAdmin() || $currentUser->can('pod.reconcile') || in_array($currentUser->role, ['administrator', 'super administrator'], true));
        $canUpdateStatus = $currentUser && ($currentUser->isSuperAdmin() || $currentUser->can('orders.update_status') || in_array($currentUser->role, ['administrator', 'super administrator'], true));

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order->load(['items.product', 'customer', 'assignedStaff', 'createdByStaff', 'remittedByStaff', 'reconciledByAdmin', 'statusHistories.user']),
            'allStaff' => \App\Models\User::whereIn('role', ['staff', 'administrator', 'super administrator'])
                ->select('id', 'name', 'role')
                ->orderBy('name')
                ->get(),
            'canReconcile' => $canReconcile,
            'canUpdateStatus' => $canUpdateStatus,
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
        $user = $request->user();
        $canUpdate = $user && (
            $user->isSuperAdmin() ||
            $user->can('orders.update_status') ||
            in_array($user->role, ['administrator', 'super administrator'], true)
        );

        if (!$canUpdate) {
            return back()->with('error', 'Unauthorized. You do not have permission to modify order fulfillment status.');
        }

        if (in_array($order->status, ['completed', 'cancelled'], true)) {
            $formatted = ucfirst($order->status);
            return back()->with('error', "This order is permanently {$formatted} and its status cannot be modified.");
        }

        $validated = $request->validate([
            'status' => 'required|string|in:pending,paid,processing,shipped,completed,cancelled'
        ]);

        $newStatus = $validated['status'];
        $oldStatus = $order->status;

        $order->update(['status' => $newStatus]);

        $user = $request->user();
        $userName = $user ? $user->name : 'System';
        $oldFormatted = ucwords(str_replace('_', ' ', $oldStatus));
        $newFormatted = ucwords(str_replace('_', ' ', $newStatus));

        if ($oldStatus !== $newStatus) {
            $order->recordStatusHistory(
                toStatus: $newStatus,
                fromStatus: $oldStatus,
                event: 'status_change',
                comment: "Order status updated from {$oldFormatted} to {$newFormatted} by {$userName}."
            );

            // Notify customer of order status change via queue
            $customerEmail = $order->billing_email ?? $order->customer?->email ?? $order->guest_email;
            if (!empty($customerEmail)) {
                try {
                    \Illuminate\Support\Facades\Mail::to($customerEmail)->queue(new \App\Mail\OrderStatusUpdatedMail($order, $newStatus));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Could not queue order status update email for Order #{$order->id}: " . $e->getMessage());
                }
            }
        } else {
            $order->recordStatusHistory(
                toStatus: $newStatus,
                fromStatus: $oldStatus,
                event: 'status_change',
                comment: "Order status confirmed as {$newFormatted} by {$userName}."
            );
        }

        // When moving to processing, paid, shipped, or completed (e.g. POD order fulfilled)
        if (in_array($newStatus, ['paid', 'processing', 'shipped', 'completed'], true)) {
            if ($order->pos_reservation_status === 'reserved') {
                $this->reservationService->confirmReservation($order);
            }

            // Transition POD order to active delivery transit once marked shipped
            if ($newStatus === 'shipped' && $order->payment_method === 'pod' && $order->pod_status === 'pending_dispatch') {
                $order->update(['pod_status' => 'awaiting_collection']);
            }

            // Affiliate commission transition on order completion
            if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                app(\App\Services\Referral\AffiliateCommissionService::class)->handleOrderCompleted($order);
            }
        }

        // When cancelled, restore ecommerce inventory and release POS reservation (Section 140M)
        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
            $this->inventoryService->restoreOrderInventory($order);
            app(\App\Services\Referral\AffiliateCommissionService::class)->handleOrderCancelled($order, 'Order status updated to cancelled');
        }

        ActivityLogger::order("Updated order #{$order->tracking_number} status to '{$newStatus}'", [
            'order_id'        => $order->id,
            'tracking_number' => $order->tracking_number,
            'old_status'      => $oldStatus,
            'new_status'      => $newStatus,
        ]);

        return back()->with('success', 'Order status updated.');
    }

    /**
     * Record an audit note on the order lifecycle timeline.
     */
    public function addNote(Request $request, Order $order)
    {
        $validated = $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        $order->recordStatusHistory(
            toStatus: $order->status,
            fromStatus: $order->status,
            event: 'note_added',
            comment: $validated['note']
        );

        ActivityLogger::order("Added timeline note on order #{$order->tracking_number}", [
            'order_id'        => $order->id,
            'tracking_number' => $order->tracking_number,
            'note'            => $validated['note'],
        ]);

        return back()->with('success', 'Order note recorded in history timeline.');
    }
}
