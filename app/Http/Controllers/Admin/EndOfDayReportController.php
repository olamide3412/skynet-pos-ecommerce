<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StoreSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EndOfDayReportController extends Controller
{
    /**
     * Display the End of Day (EOD) closing report.
     */
    public function index(Request $request)
    {
        $dateInput = $request->input('date');

        try {
            $selectedDate = $dateInput ? Carbon::parse($dateInput)->startOfDay() : Carbon::today();
        } catch (\Exception $e) {
            $selectedDate = Carbon::today();
        }

        $startOfDay = $selectedDate->copy()->startOfDay();
        $endOfDay   = $selectedDate->copy()->endOfDay();

        $prevDate = $selectedDate->copy()->subDay()->format('Y-m-d');
        $nextDate = $selectedDate->copy()->addDay()->format('Y-m-d');
        $isToday  = $selectedDate->isToday();

        // 1. Base Orders Query for the selected day (including POD collections/reconciliations today)
        $allOrdersForDay = Order::where(function ($q) use ($startOfDay, $endOfDay) {
            $q->whereBetween('created_at', [$startOfDay, $endOfDay])
              ->orWhereBetween('pod_collected_at', [$startOfDay, $endOfDay])
              ->orWhereBetween('pod_reconciled_at', [$startOfDay, $endOfDay]);
        });
        $paidOrdersQuery = (clone $allOrdersForDay)->whereNotIn('status', ['pending', 'cancelled']);

        // 2. Line Items Expressions (cross-database compatible)
        $lineRevenueExpr  = '(order_items.price * order_items.quantity)';
        $unitBaseCostExpr = 'COALESCE(NULLIF(products.cost_price, 0), NULLIF(synced_pos_items.buying_price, 0), 0)';
        $baseQtyExpr      = 'COALESCE(NULLIF(order_items.quantity_in_base_units, 0), order_items.quantity)';
        $lineCostExpr     = "CASE WHEN order_items.cost_price > 0 THEN (order_items.cost_price * order_items.quantity) ELSE ({$unitBaseCostExpr} * {$baseQtyExpr}) END";

        $itemsBaseQuery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('synced_pos_items', 'synced_pos_items.pos_item_id', '=', 'products.pos_item_id')
            ->whereNotIn('orders.status', ['pending', 'cancelled'])
            ->where(function ($q) use ($startOfDay, $endOfDay) {
                $q->whereBetween('orders.created_at', [$startOfDay, $endOfDay])
                  ->orWhereBetween('orders.pod_collected_at', [$startOfDay, $endOfDay])
                  ->orWhereBetween('orders.pod_reconciled_at', [$startOfDay, $endOfDay]);
            });

        // 3. Financial Totals for Paid Orders
        $totalsRow = (clone $itemsBaseQuery)
            ->selectRaw("
                COALESCE(SUM({$lineRevenueExpr}), 0) as items_revenue,
                COALESCE(SUM({$lineCostExpr}), 0) as items_cost,
                COALESCE(SUM(order_items.quantity), 0) as items_sold
            ")
            ->first();

        $paidOrdersSum = (float) (clone $paidOrdersQuery)->sum('total_amount');
        $totalRevenue  = $paidOrdersSum > 0 ? $paidOrdersSum : (float) ($totalsRow->items_revenue ?? 0);

        $totalCost    = (float) ($totalsRow->items_cost ?? 0);
        $totalProfit  = $totalRevenue - $totalCost;
        $profitMargin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : 0;
        $itemsSold    = (int) ($totalsRow->items_sold ?? 0);

        $paidOrdersCount      = (int) (clone $paidOrdersQuery)->count();
        $totalOrdersCount     = (int) (clone $allOrdersForDay)->count();
        $pendingOrdersCount   = (int) (clone $allOrdersForDay)->where('status', 'pending')->count();
        $cancelledOrdersCount = (int) (clone $allOrdersForDay)->where('status', 'cancelled')->count();
        $totalDiscounts       = (float) (clone $paidOrdersQuery)->sum('discount_amount');
        $averageOrderValue    = $paidOrdersCount > 0 ? $totalRevenue / $paidOrdersCount : 0;

        // 4. Source Breakdown (POS vs Ecommerce)
        $sourceRows = (clone $itemsBaseQuery)
            ->selectRaw("
                order_items.inventory_source as source,
                COALESCE(SUM(order_items.quantity), 0) as units_sold,
                COALESCE(SUM({$lineRevenueExpr}), 0) as revenue,
                COALESCE(SUM({$lineCostExpr}), 0) as cost
            ")
            ->groupBy('order_items.inventory_source')
            ->get()
            ->keyBy('source');

        $posRow  = $sourceRows->get('pos');
        $ecomRow = $sourceRows->get('ecommerce');

        $posRevenue = (float) ($posRow->revenue ?? 0);
        $posCost    = (float) ($posRow->cost ?? 0);
        $posProfit  = $posRevenue - $posCost;
        $posUnits   = (int) ($posRow->units_sold ?? 0);
        $posMargin  = $posRevenue > 0 ? round(($posProfit / $posRevenue) * 100, 1) : 0;

        $ecomRevenue = (float) ($ecomRow->revenue ?? 0);
        $ecomCost    = (float) ($ecomRow->cost ?? 0);
        $ecomProfit  = $ecomRevenue - $ecomCost;
        $ecomUnits   = (int) ($ecomRow->units_sold ?? 0);
        $ecomMargin  = $ecomRevenue > 0 ? round(($ecomProfit / $ecomRevenue) * 100, 1) : 0;

        // 5. Payment Method Reconciliation (MySQL ONLY_FULL_GROUP_BY safe)
        $paymentMethods = (clone $allOrdersForDay)
            ->selectRaw("
                payment_method,
                COUNT(*) as total_orders,
                COALESCE(SUM(CASE WHEN status NOT IN ('pending', 'cancelled') THEN total_amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END), 0) as pending_amount,
                COALESCE(SUM(CASE WHEN status NOT IN ('pending', 'cancelled') THEN 1 ELSE 0 END), 0) as paid_orders
            ")
            ->groupBy('payment_method')
            ->get()
            ->groupBy(function ($item) {
                $m = strtolower(trim($item->payment_method ?? ''));
                if (in_array($m, ['cod', 'pod'], true)) {
                    return 'Payment on Delivery (POD)';
                }
                if ($m === 'bank_transfer') {
                    return 'Direct Bank Transfer';
                }
                return $m !== '' ? ucwords(str_replace('_', ' ', $m)) : 'Unspecified';
            })
            ->map(function ($group, $methodName) use ($totalRevenue) {
                $totalOrders   = (int) $group->sum('total_orders');
                $paidOrders    = (int) $group->sum('paid_orders');
                $paidAmount    = (float) $group->sum('paid_amount');
                $pendingAmount = (float) $group->sum('pending_amount');
                $percentage    = $totalRevenue > 0 ? round(($paidAmount / $totalRevenue) * 100, 1) : 0;

                return [
                    'method'         => $methodName,
                    'total_orders'   => $totalOrders,
                    'paid_orders'    => $paidOrders,
                    'paid_amount'    => $paidAmount,
                    'pending_amount' => $pendingAmount,
                    'percentage'     => $percentage,
                ];
            })
            ->values();

        // 6. Order Status Distribution
        $statusStats = (clone $allOrdersForDay)
            ->selectRaw("
                status,
                COUNT(*) as count,
                COALESCE(SUM(total_amount), 0) as total_amount
            ")
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                $item->total_amount = (float) $item->total_amount;
                return $item;
            });

        // 7. Hourly Sales Timeline (00:00 to 23:00) computed in PHP
        $hourlyMap = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyMap[$h] = [
                'hour'    => sprintf('%02d:00', $h),
                'label'   => Carbon::createFromTime($h, 0)->format('g A'),
                'orders'  => 0,
                'revenue' => 0.0,
            ];
        }

        $paidOrdersToday = (clone $paidOrdersQuery)
            ->select('id', 'total_amount', 'created_at', 'pod_collected_at', 'pod_reconciled_at')
            ->get();

        foreach ($paidOrdersToday as $ord) {
            $effectiveDate = Carbon::parse($ord->created_at);
            if (!$effectiveDate->between($startOfDay, $endOfDay)) {
                if ($ord->pod_collected_at && Carbon::parse($ord->pod_collected_at)->between($startOfDay, $endOfDay)) {
                    $effectiveDate = Carbon::parse($ord->pod_collected_at);
                } elseif ($ord->pod_reconciled_at && Carbon::parse($ord->pod_reconciled_at)->between($startOfDay, $endOfDay)) {
                    $effectiveDate = Carbon::parse($ord->pod_reconciled_at);
                }
            }
            $orderHour = $effectiveDate->hour;
            if (isset($hourlyMap[$orderHour])) {
                $hourlyMap[$orderHour]['orders']++;
                $hourlyMap[$orderHour]['revenue'] += (float) $ord->total_amount;
            }
        }
        $hourlyStats = array_values($hourlyMap);

        // 8. Products Sold on this Day
        $productsSold = (clone $itemsBaseQuery)
            ->selectRaw("
                order_items.product_id,
                COALESCE(products.name, 'Deleted Product') as product_name,
                order_items.inventory_source,
                COALESCE(order_items.unit_name, '-') as unit_name,
                SUM(order_items.quantity) as qty_sold,
                COALESCE(SUM({$lineRevenueExpr}), 0) as revenue,
                COALESCE(SUM({$lineCostExpr}), 0) as cost
            ")
            ->groupBy(
                'order_items.product_id',
                'products.name',
                'order_items.inventory_source',
                'order_items.unit_name'
            )
            ->orderByDesc('revenue')
            ->get()
            ->map(function ($row) {
                $row->revenue = (float) $row->revenue;
                $row->cost    = (float) $row->cost;
                $row->profit  = $row->revenue - $row->cost;
                return $row;
            });

        // 9. Detailed Orders List for the Day
        $ordersList = (clone $allOrdersForDay)
            ->with(['items.product'])
            ->latest()
            ->get()
            ->map(function ($order) {
                return [
                    'id'              => $order->id,
                    'tracking_number' => $order->tracking_number,
                    'time'            => Carbon::parse($order->created_at)->format('h:i A'),
                    'customer_name'   => $order->billing_name ?: 'Guest Customer',
                    'customer_email'  => $order->billing_email ?: $order->guest_email,
                    'payment_method'  => match ($order->payment_method) {
                        'cod', 'pod'    => 'Payment on Delivery (POD)',
                        'bank_transfer' => 'Direct Bank Transfer',
                        default         => $order->payment_method ? ucwords(str_replace('_', ' ', $order->payment_method)) : 'Not Specified',
                    },
                    'status'          => $order->status,
                    'total_amount'    => (float) $order->total_amount,
                    'discount_amount' => (float) ($order->discount_amount ?? 0),
                    'items_count'     => (int) $order->items->sum('quantity'),
                    'has_pos_items'   => $order->items->contains('inventory_source', 'pos'),
                ];
            });

        // 10. Store Settings
        $storeInfo = [
            'company_name'    => StoreSetting::get('company_name', 'Skynet'),
            'company_tagline' => StoreSetting::get('company_tagline', 'E-Commerce & POS'),
            'company_address' => StoreSetting::get('company_address', ''),
            'company_phone'   => StoreSetting::get('company_phone', ''),
            'company_email'   => StoreSetting::get('company_email', ''),
        ];

        return Inertia::render('Admin/Reports/EndOfDay', [
            'date'            => $selectedDate->format('Y-m-d'),
            'formatted_date'  => $selectedDate->format('l, F j, Y'),
            'prev_date'       => $prevDate,
            'next_date'       => $nextDate,
            'is_today'        => $isToday,
            'metrics'         => [
                'total_revenue'          => $totalRevenue,
                'total_cost'             => $totalCost,
                'total_profit'           => $totalProfit,
                'profit_margin'          => $profitMargin,
                'items_sold'             => $itemsSold,
                'total_discounts'        => $totalDiscounts,
                'average_order_value'    => $averageOrderValue,
                'paid_orders_count'      => $paidOrdersCount,
                'total_orders_count'     => $totalOrdersCount,
                'pending_orders_count'   => $pendingOrdersCount,
                'cancelled_orders_count' => $cancelledOrdersCount,
            ],
            'source_breakdown' => [
                'pos' => [
                    'units_sold' => $posUnits,
                    'revenue'    => $posRevenue,
                    'cost'       => $posCost,
                    'profit'     => $posProfit,
                    'margin'     => $posMargin,
                ],
                'ecommerce' => [
                    'units_sold' => $ecomUnits,
                    'revenue'    => $ecomRevenue,
                    'cost'       => $ecomCost,
                    'profit'     => $ecomProfit,
                    'margin'     => $ecomMargin,
                ],
            ],
            'payment_methods' => $paymentMethods,
            'status_stats'    => $statusStats,
            'hourly_stats'    => $hourlyStats,
            'products_sold'   => $productsSold,
            'orders'          => $ordersList,
            'store_info'      => $storeInfo,
            'generated_at'    => Carbon::now()->format('M d, Y h:i A'),
        ]);
    }
}

