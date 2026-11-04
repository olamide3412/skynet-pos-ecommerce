<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RevenueController extends Controller
{
    public function index(Request $request)
    {
        $user = auth('web')->user();
        if (!$user->isSuperAdmin() && !in_array($user->role, [\App\Enums\RoleEnums::Administrator->value, \App\Enums\RoleEnums::SuperAdministrator->value], true) && !$user->can('reports.revenue_profit')) {
            abort(403, 'Unauthorized. Revenue analytics is restricted to administrators and authorized financial staff.');
        }

        // Default to last 30 days if no dates provided
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : Carbon::now()->subDays(29)->startOfDay();

        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        $inventorySource = $request->input('inventory_source', ''); // '', 'pos', or 'ecommerce'
        if (!in_array($inventorySource, ['pos', 'ecommerce'], true)) {
            $inventorySource = '';
        }

        // Base query for paid/active orders within the date range (including POD collections/reconciliations in date range)
        $ordersQuery = Order::whereNotIn('status', ['pending', 'cancelled'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                  ->orWhereBetween('pod_collected_at', [$startDate, $endDate])
                  ->orWhereBetween('pod_reconciled_at', [$startDate, $endDate]);
            });

        if ($inventorySource !== '') {
            $ordersQuery->whereHas('items', function ($q) use ($inventorySource) {
                $q->where('inventory_source', $inventorySource);
            });
        }

        $totalOrders = (int) (clone $ordersQuery)->count();

        // SQL expressions for line-item revenue and cost
        // Cost uses snapshotted order_items.cost_price when > 0; otherwise falls back to products.cost_price or synced_pos_items.buying_price * base units
        $lineRevenueExpr = '(order_items.price * order_items.quantity)';
        $unitBaseCostExpr = 'COALESCE(NULLIF(products.cost_price, 0), NULLIF(synced_pos_items.buying_price, 0), 0)';
        $baseQtyExpr = 'COALESCE(NULLIF(order_items.quantity_in_base_units, 0), order_items.quantity)';
        $lineCostExpr = "CASE WHEN order_items.cost_price > 0 THEN (order_items.cost_price * order_items.quantity) ELSE ({$unitBaseCostExpr} * {$baseQtyExpr}) END";

        // Base order_items query joined with paid orders, products, and synced_pos_items
        $itemsBaseQuery = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('synced_pos_items', 'synced_pos_items.pos_item_id', '=', 'products.pos_item_id')
            ->whereNotIn('orders.status', ['pending', 'cancelled'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('orders.created_at', [$startDate, $endDate])
                  ->orWhereBetween('orders.pod_collected_at', [$startDate, $endDate])
                  ->orWhereBetween('orders.pod_reconciled_at', [$startDate, $endDate]);
            });

        // Breakdown by source (POS vs Ecommerce) using plain column groupBy for MySQL ONLY_FULL_GROUP_BY compatibility
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

        $posRevenue = (float) ($sourceRows->get('pos')->revenue ?? 0);
        $posCost    = (float) ($sourceRows->get('pos')->cost ?? 0);
        $posProfit  = $posRevenue - $posCost;
        $posUnits   = (int) ($sourceRows->get('pos')->units_sold ?? 0);
        $posMargin  = $posRevenue > 0 ? round(($posProfit / $posRevenue) * 100, 1) : 0;

        $ecomRevenue = (float) ($sourceRows->get('ecommerce')->revenue ?? 0);
        $ecomCost    = (float) ($sourceRows->get('ecommerce')->cost ?? 0);
        $ecomProfit  = $ecomRevenue - $ecomCost;
        $ecomUnits   = (int) ($sourceRows->get('ecommerce')->units_sold ?? 0);
        $ecomMargin  = $ecomRevenue > 0 ? round(($ecomProfit / $ecomRevenue) * 100, 1) : 0;

        // Filtered totals based on selected inventory_source
        if ($inventorySource === 'pos') {
            $totalRevenue = $posRevenue;
            $totalCost    = $posCost;
        } elseif ($inventorySource === 'ecommerce') {
            $totalRevenue = $ecomRevenue;
            $totalCost    = $ecomCost;
        } else {
            $itemsTotalRevenue = $posRevenue + $ecomRevenue;
            $ordersTotalAmount = (float) (clone $ordersQuery)->sum('total_amount');
            $totalRevenue = $ordersTotalAmount > 0 ? $ordersTotalAmount : $itemsTotalRevenue;
            $totalCost    = $posCost + $ecomCost;
        }

        $totalProfit = $totalRevenue - $totalCost;
        $profitMargin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : 0;
        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // Apply source filter for daily & product tables if chosen
        $filteredItemsQuery = clone $itemsBaseQuery;
        if ($inventorySource !== '') {
            $filteredItemsQuery->where('order_items.inventory_source', $inventorySource);
        }

        // Daily breakdown with POS profit, Ecommerce profit, Total Revenue, Total Cost, Total Profit
        $startDateStr = $startDate->toDateTimeString();
        $endDateStr   = $endDate->toDateTimeString();
        $orderDateExpr = "DATE(CASE 
            WHEN orders.created_at BETWEEN '{$startDateStr}' AND '{$endDateStr}' THEN orders.created_at 
            WHEN orders.pod_collected_at BETWEEN '{$startDateStr}' AND '{$endDateStr}' THEN orders.pod_collected_at 
            WHEN orders.pod_reconciled_at BETWEEN '{$startDateStr}' AND '{$endDateStr}' THEN orders.pod_reconciled_at 
            ELSE orders.created_at 
        END)";

        $dailyItems = (clone $filteredItemsQuery)
            ->selectRaw("
                {$orderDateExpr} as date,
                COUNT(DISTINCT orders.id) as orders,
                COALESCE(SUM({$lineRevenueExpr}), 0) as revenue,
                COALESCE(SUM({$lineCostExpr}), 0) as cost,
                COALESCE(SUM(CASE WHEN order_items.inventory_source = 'pos' THEN ({$lineRevenueExpr}) - ({$lineCostExpr}) ELSE 0 END), 0) as pos_profit,
                COALESCE(SUM(CASE WHEN order_items.inventory_source = 'ecommerce' THEN ({$lineRevenueExpr}) - ({$lineCostExpr}) ELSE 0 END), 0) as ecommerce_profit
            ")
            ->groupBy(DB::raw('date'))
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($day) {
                $day->revenue          = (float) $day->revenue;
                $day->cost             = (float) $day->cost;
                $day->profit           = $day->revenue - $day->cost;
                $day->pos_profit       = (float) $day->pos_profit;
                $day->ecommerce_profit = (float) $day->ecommerce_profit;
                return $day;
            });

        // Product-level profit breakdown (Top 50 products in period)
        $productProfits = (clone $filteredItemsQuery)
            ->selectRaw("
                order_items.product_id,
                COALESCE(products.name, 'Deleted Product') as product_name,
                order_items.inventory_source,
                COALESCE(order_items.pos_item_id, products.pos_item_id) as pos_item_id,
                SUM(order_items.quantity) as qty_sold,
                COALESCE(SUM({$lineRevenueExpr}), 0) as revenue,
                COALESCE(SUM({$lineCostExpr}), 0) as cost
            ")
            ->groupBy(
                'order_items.product_id',
                'products.name',
                'order_items.inventory_source',
                'order_items.pos_item_id',
                'products.pos_item_id'
            )
            ->orderByDesc('revenue')
            ->limit(50)
            ->get()
            ->map(function ($row) {
                $row->revenue = (float) $row->revenue;
                $row->cost    = (float) $row->cost;
                $row->profit  = $row->revenue - $row->cost;
                return $row;
            });

        // Revenue by payment method (MySQL ONLY_FULL_GROUP_BY safe)
        $revenueByMethod = (clone $ordersQuery)
            ->select('payment_method')
            ->selectRaw('SUM(total_amount) as revenue, COUNT(*) as orders')
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
            ->map(function ($group, $methodName) {
                return (object) [
                    'payment_method' => $methodName,
                    'revenue'        => (float) $group->sum('revenue'),
                    'orders'         => (int) $group->sum('orders'),
                ];
            })
            ->values();

        return Inertia::render('Admin/Revenue/Index', [
            'metrics' => [
                'total_revenue' => $totalRevenue,
                'total_cost'    => $totalCost,
                'total_profit'  => $totalProfit,
                'profit_margin' => $profitMargin,
                'total_orders'  => $totalOrders,
                'average_value' => $averageOrderValue,
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
            'chart_data'      => $dailyItems,
            'product_profits' => $productProfits,
            'method_stats'    => $revenueByMethod,
            'filters'         => [
                'start_date'       => $startDate->format('Y-m-d'),
                'end_date'         => $endDate->format('Y-m-d'),
                'inventory_source' => $inventorySource,
            ],
        ]);
    }
}
