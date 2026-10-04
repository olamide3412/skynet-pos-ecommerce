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
        // Default to last 30 days if no dates provided
        $startDate = $request->input('start_date') 
            ? Carbon::parse($request->input('start_date'))->startOfDay() 
            : Carbon::now()->subDays(29)->startOfDay();
            
        $endDate = $request->input('end_date') 
            ? Carbon::parse($request->input('end_date'))->endOfDay() 
            : Carbon::now()->endOfDay();

        // Base query for paid orders within the date range (status is typically 'processing' or 'completed' when paid)
        $query = Order::whereNotIn('status', ['pending', 'cancelled'])
                      ->whereBetween('created_at', [$startDate, $endDate]);

        $totalRevenue = (clone $query)->sum('total_amount');
        $totalOrders = (clone $query)->count();
        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // Revenue over time (daily)
        $revenueByDay = (clone $query)
            ->select(
                DB::raw('DATE(created_at) as date'), 
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->get();

        // Revenue by payment method
        $revenueByMethod = (clone $query)
            ->select(
                'payment_method',
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('payment_method')
            ->get();

        return Inertia::render('Admin/Revenue/Index', [
            'metrics' => [
                'total_revenue' => $totalRevenue,
                'total_orders'  => $totalOrders,
                'average_value' => $averageOrderValue,
            ],
            'chart_data'   => $revenueByDay,
            'method_stats' => $revenueByMethod,
            'filters'      => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date'   => $endDate->format('Y-m-d'),
            ]
        ]);
    }
}
