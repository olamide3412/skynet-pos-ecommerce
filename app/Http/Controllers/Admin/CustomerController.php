<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    /**
     * Display a listing of registered storefront customers.
     */
    public function index(Request $request)
    {
        $query = Customer::query()
            ->withCount(['orders', 'addresses', 'reviews'])
            ->withSum('orders', 'total_amount');

        // Search by Name, Email, or Phone
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by Registration Provider
        if ($request->filled('provider') && $request->input('provider') !== 'all') {
            $provider = $request->input('provider');
            if ($provider === 'email') {
                $query->whereNull('provider_name');
            } else {
                $query->where('provider_name', $provider);
            }
        }

        // Sorting
        $sort = $request->input('sort', 'latest');
        match ($sort) {
            'oldest' => $query->oldest(),
            'orders_high' => $query->orderByDesc('orders_count'),
            'spend_high' => $query->orderByDesc('orders_sum_total_amount'),
            'name_asc' => $query->orderBy('name', 'asc'),
            default => $query->latest(),
        };

        $customers = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => Customer::count(),
            'new_this_month' => Customer::where('created_at', '>=', now()->startOfMonth())->count(),
            'with_orders' => Customer::has('orders')->count(),
            'social_logins' => Customer::whereNotNull('provider_name')->count(),
        ];

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $request->input('search', ''),
                'provider' => $request->input('provider', 'all'),
                'sort' => $sort,
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Display the specified customer details and history.
     */
    public function show(Customer $customer)
    {
        $customer->loadCount(['orders', 'addresses', 'reviews']);
        $customer->loadSum('orders', 'total_amount');

        $orders = $customer->orders()
            ->with(['items'])
            ->latest()
            ->paginate(10);

        $addresses = $customer->addresses()->latest()->get();

        return Inertia::render('Admin/Customers/Show', [
            'customer' => $customer,
            'orders' => $orders,
            'addresses' => $addresses,
        ]);
    }

    /**
     * Update customer profile record.
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customer->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $original = [
            'name'  => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
        ];

        $customer->update($validated);

        $changes = [];
        foreach ($validated as $key => $newVal) {
            if ($original[$key] !== $customer->{$key}) {
                $changes[$key] = [
                    'old' => $original[$key],
                    'new' => $customer->{$key},
                ];
            }
        }

        ActivityLogger::customer("Updated customer profile for {$customer->name} (#{$customer->id})", [
            'customer_id'    => $customer->id,
            'customer_name'  => $customer->name,
            'customer_email' => $customer->email,
            'changes'        => $changes,
        ]);

        return back()->with('success', "Customer record for {$customer->name} updated successfully.");
    }

    /**
     * Reset password for customer.
     */
    public function resetPassword(Request $request, Customer $customer)
    {
        $request->validate([
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $customer->update([
            'password' => Hash::make($request->input('password')),
        ]);

        ActivityLogger::customer("Reset password for customer {$customer->name} (#{$customer->id})", [
            'customer_id'    => $customer->id,
            'customer_name'  => $customer->name,
            'customer_email' => $customer->email,
        ]);

        return back()->with('success', "Password for {$customer->name} reset successfully.");
    }

    /**
     * Remove customer account.
     */
    public function destroy(Customer $customer)
    {
        $name = $customer->name;
        $id = $customer->id;
        $email = $customer->email;
        $ordersCount = $customer->orders()->count();

        // If customer has orders, dissociate customer_id to preserve financial history
        if ($customer->orders()->exists()) {
            $customer->orders()->update(['customer_id' => null]);
        }

        $customer->delete();

        ActivityLogger::customer("Deleted customer account for {$name} (#{$id})", [
            'customer_id'        => $id,
            'customer_name'      => $name,
            'customer_email'     => $email,
            'dissociated_orders' => $ordersCount,
        ]);

        return redirect()->route('admin.customers.index')
            ->with('success', "Customer account for {$name} deleted successfully.");
    }

    /**
     * Export registered customers to CSV.
     */
    public function exportCsv(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="customers_export_' . date('Y-m-d') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Name', 'Email', 'Phone', 'Auth Provider', 'Total Orders', 'Total Spent (NGN)', 'Registered Date']);

            Customer::query()
                ->withCount('orders')
                ->withSum('orders', 'total_amount')
                ->chunk(200, function ($customers) use ($handle) {
                    foreach ($customers as $c) {
                        fputcsv($handle, [
                            $c->id,
                            $c->name,
                            $c->email,
                            $c->phone ?? '',
                            $c->provider_name ?? 'Email/Password',
                            $c->orders_count,
                            number_format($c->orders_sum_total_amount ?? 0, 2, '.', ''),
                            $c->created_at->toDateTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }
}

