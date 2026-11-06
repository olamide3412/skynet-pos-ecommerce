<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnums;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosConnection;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth('web')->user();

        $canViewFinancials = $user->isSuperAdmin()
            || in_array($user->role, [RoleEnums::Administrator->value, RoleEnums::SuperAdministrator->value], true)
            || $user->can('dashboard.view_financials');
        $isAdmin = $canViewFinancials;

        $today = Carbon::today();
        $now = Carbon::now();

        // Check if user is a dedicated Rider / Dispatch profile
        $isRider = !$user->isSuperAdmin() && $user->hasRole('Rider / Dispatch') && !$user->can('orders.view_all');

        // ── Shared Operational Metrics (Admin & Staff) ─────────────
        $ordersBase = Order::query();
        if ($isRider) {
            $ordersBase->where('assigned_staff_id', $user->id);
        }

        $totalOrdersCount     = (clone $ordersBase)->count();
        $todayOrdersCount     = (clone $ordersBase)->whereDate('created_at', $today)->count();
        $pendingOrdersCount   = (clone $ordersBase)->whereIn('status', ['pending', 'processing', 'shipped'])->count();
        $completedOrdersCount = (clone $ordersBase)->where('status', 'completed')->count();

        // Stock alerts (hidden/0 for dedicated riders)
        $lowStockCount        = $isRider ? 0 : Product::where('stock', '>', 0)->where('stock', '<=', 5)->count();
        $outOfStockCount      = $isRider ? 0 : Product::where('stock', '<=', 0)->count();
        $totalProductsCount   = $isRider ? 0 : Product::count();

        // Top 5 products running low or out of stock
        $lowStockProducts = $isRider ? [] : Product::select('id', 'name', 'sku', 'slug', 'stock', 'price', 'image', 'inventory_source')
            ->where('stock', '<=', 5)
            ->orderBy('stock', 'asc')
            ->limit(5)
            ->get();

        // POD Cash float in custody (Cash collected from customers but not yet remitted to store)
        $podCashInHand = (float) Order::where(function ($q) use ($user) {
                $q->where('assigned_staff_id', $user->id)
                  ->orWhere('remitted_by_staff_id', $user->id);
            })
            ->where('payment_method', 'pod')
            ->where('pod_status', 'cash_collected')
            ->sum(DB::raw('COALESCE(pod_amount_collected, total_amount)'));

        $podCashInHandCount = Order::where(function ($q) use ($user) {
                $q->where('assigned_staff_id', $user->id)
                  ->orWhere('remitted_by_staff_id', $user->id);
            })
            ->where('payment_method', 'pod')
            ->where('pod_status', 'cash_collected')
            ->count();

        $podRemittedPendingReconcile = (float) Order::where(function ($q) use ($user) {
                $q->where('assigned_staff_id', $user->id)
                  ->orWhere('remitted_by_staff_id', $user->id);
            })
            ->where('payment_method', 'pod')
            ->where('pod_status', 'remitted_to_store')
            ->sum(DB::raw('COALESCE(pod_amount_collected, total_amount)'));

        $podRemittedToday = (float) Order::where(function ($q) use ($user) {
                $q->where('assigned_staff_id', $user->id)
                  ->orWhere('remitted_by_staff_id', $user->id);
            })
            ->where('payment_method', 'pod')
            ->whereIn('pod_status', ['remitted_to_store', 'reconciled'])
            ->whereDate('pod_remitted_at', $today)
            ->sum(DB::raw('COALESCE(pod_amount_collected, total_amount)'));

        // POS Connection status
        $posConnection = PosConnection::select('is_connected', 'connected_at', 'pos_branch_id', 'pos_branch_name')->first();

        // Order transformer for dashboard views
        $mapOrder = function ($order) use ($isAdmin, $isRider) {
            $hasPosItems = $order->items ? $order->items->contains(function ($item) {
                return $item->inventory_source === 'pos' || !empty($item->pos_item_id);
            }) : false;

            return [
                'id'                    => $order->id,
                'customer_name'         => $order->customer ? $order->customer->name : ($order->guest_email ? 'Guest (' . $order->guest_email . ')' : 'Walk-in / Unspecified'),
                'total_amount'          => ($isAdmin || $isRider) ? (float) $order->total_amount : null,
                'pod_status'            => $order->pod_status,
                'pod_amount_collected'  => (float) ($order->pod_amount_collected ?: $order->total_amount),
                'items_count'           => $order->items ? $order->items->sum('quantity') : 1,
                'status'                => $order->status,
                'payment_method'        => $order->payment_method,
                'is_pos_sale'           => $hasPosItems,
                'created_at'            => $order->created_at ? $order->created_at->diffForHumans() : 'Recently',
                'created_date'          => $order->created_at ? $order->created_at->format('M d, H:i') : '',
            ];
        };

        // Recent orders (latest 10)
        $recentOrders = (clone $ordersBase)->with(['customer', 'items'])
            ->latest()
            ->limit(10)
            ->get()
            ->map($mapOrder);

        // Today's orders alone
        $todayOrders = (clone $ordersBase)->with(['customer', 'items'])
            ->whereDate('created_at', $today)
            ->latest()
            ->limit(50)
            ->get()
            ->map($mapOrder);

        // ── Financial & Executive Metrics (STRICTLY Admin & Super Admin) ──
        $financialStats = null;
        $salesTrend = [];
        $paymentMethodsSplit = [];
        $channelSplit = [];

        if ($isAdmin) {
            $completedOrders = Order::where('status', 'completed');
            $totalRevenue = (float) (clone $completedOrders)->sum('total_amount');
            $todayRevenue = (float) Order::where('status', 'completed')->whereDate('created_at', $today)->sum('total_amount');
            $averageOrderValue = $totalOrdersCount > 0 ? round($totalRevenue / max(1, $completedOrdersCount), 2) : 0;

            // POS orders vs eCommerce orders count
            $posOrdersCount = Order::whereHas('items', function ($q) {
                $q->where('inventory_source', 'pos')->orWhereNotNull('pos_item_id');
            })->count();

            $ecommerceOrdersCount = max(0, $totalOrdersCount - $posOrdersCount);

            // Channel revenue
            $posRevenue = (float) OrderItem::whereHas('order', function ($q) {
                $q->where('status', 'completed');
            })->where(function ($q) {
                $q->where('inventory_source', 'pos')->orWhereNotNull('pos_item_id');
            })->sum(DB::raw('price * quantity'));

            $ecommerceRevenue = max(0, $totalRevenue - $posRevenue);

            $channelSplit = [
                'pos_orders'        => $posOrdersCount,
                'ecommerce_orders'  => $ecommerceOrdersCount,
                'pos_revenue'       => $posRevenue,
                'ecommerce_revenue' => $ecommerceRevenue,
                'pos_percent'       => $totalOrdersCount > 0 ? round(($posOrdersCount / $totalOrdersCount) * 100) : 0,
                'ecommerce_percent' => $totalOrdersCount > 0 ? round(($ecommerceOrdersCount / $totalOrdersCount) * 100) : 0,
            ];

            // 7-day Sales Trend
            for ($i = 6; $i >= 0; $i--) {
                $dayDate = Carbon::today()->subDays($i);
                $dayLabel = $dayDate->format('D'); // Mon, Tue...
                $dateShort = $dayDate->format('M d'); // Oct 26

                $dayRevenue = (float) Order::where('status', 'completed')
                    ->whereDate('created_at', $dayDate)
                    ->sum('total_amount');

                $dayOrders = Order::whereDate('created_at', $dayDate)->count();

                $salesTrend[] = [
                    'day'     => $dayLabel,
                    'date'    => $dateShort,
                    'revenue' => $dayRevenue,
                    'orders'  => $dayOrders,
                ];
            }

            // Payment methods split
            $methods = Order::selectRaw('payment_method, COUNT(*) as count, SUM(CASE WHEN status = "completed" THEN total_amount ELSE 0 END) as revenue')
                ->groupBy('payment_method')
                ->get();

            foreach ($methods as $m) {
                $name = match (strtolower($m->payment_method ?? '')) {
                    'cod', 'pod'     => 'Payment on Delivery (POD)',
                    'bank_transfer'  => 'Direct Bank Transfer',
                    'card', 'paystack' => 'Card / Paystack',
                    default          => ucfirst($m->payment_method ?: 'Unspecified'),
                };

                $paymentMethodsSplit[] = [
                    'method'  => $name,
                    'count'   => (int) $m->count,
                    'revenue' => (float) $m->revenue,
                ];
            }

            $financialStats = [
                'total_revenue'       => $totalRevenue,
                'today_revenue'       => $todayRevenue,
                'average_order_value' => $averageOrderValue,
                'total_users'         => User::count(),
            ];
        }

        return Inertia::render('Admin/Dashboard', [
            'userRole'              => $user->role,
            'isRider'               => $isRider,
            'isAdmin'               => $isAdmin,
            'operationalStats'      => [
                'total_orders'     => $totalOrdersCount,
                'today_orders'     => $todayOrdersCount,
                'pending_orders'   => $pendingOrdersCount,
                'completed_orders' => $completedOrdersCount,
                'low_stock_count'  => $lowStockCount,
                'out_of_stock'     => $outOfStockCount,
                'total_products'   => $totalProductsCount,
            ],
            'podCustody'            => [
                'cash_in_hand'               => $podCashInHand,
                'cash_in_hand_count'         => $podCashInHandCount,
                'remitted_pending_reconcile' => $podRemittedPendingReconcile,
                'remitted_today'             => $podRemittedToday,
            ],
            'financialStats'        => $financialStats, // null for staff
            'salesTrend'            => $salesTrend,     // empty for staff
            'channelSplit'          => $channelSplit,   // empty for staff
            'paymentMethodsSplit'   => $paymentMethodsSplit, // empty for staff
            'lowStockProducts'      => $lowStockProducts,
            'recentOrders'          => $recentOrders,
            'todayOrders'           => $todayOrders,
            'posStatus'             => [
                'is_connected'      => $posConnection?->is_connected ?? false,
                'last_connected_at' => $posConnection?->connected_at ? Carbon::parse($posConnection->connected_at)->diffForHumans() : null,
                'branch_name'       => $posConnection?->pos_branch_name ?? 'Primary Store',
            ],
        ]);
    }
}
