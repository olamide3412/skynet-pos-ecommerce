<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Enums\RoleEnums;
use App\Models\Product;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth('web')->user();
        
        if ($user->role === RoleEnums::Administrator->value || $user->role === RoleEnums::SuperAdministrator->value) {
            $stats = [
                'total_orders' => Order::count(),
                'total_revenue' => Order::where('status', 'completed')->sum('total_amount'),
                'total_products' => Product::count(),
                'total_users' => User::count(),
            ];
            $recentOrders = Order::with('customer')->latest()->limit(5)->get();
            
            return Inertia::render('Admin/Dashboard', [
                'stats' => $stats,
                'recentOrders' => $recentOrders,
            ]);
        }
        
        // Non-admin web users: show their profile
        return Inertia::render('Admin/Dashboard', [
            'stats' => [],
            'recentOrders' => []
        ]);
    }
}
