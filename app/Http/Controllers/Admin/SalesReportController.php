<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportController extends Controller
{
    /**
     * Display the comprehensive Sales Report.
     */
    public function index(Request $request)
    {
        $currentUser = $request->user();
        $canViewAllSales = $currentUser->isSuperAdmin()
            || in_array($currentUser->role, ['administrator', 'super administrator'], true)
            || $currentUser->can('reports.sales.view_all');
        $isAdmin = $canViewAllSales;

        // Default or requested date range
        $preset = $request->input('preset', 'today'); // 'today', 'yesterday', 'this_week', 'this_month', 'custom'
        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        [$startDate, $endDate] = $this->resolveDateRange($preset, $startDateInput, $endDateInput);

        // Staff filter: If staff user without reports.sales.view_all, restrict to their own sales
        $staffId = $request->input('staff_id');
        if (!$canViewAllSales) {
            // Staff only view their assigned or handled orders
            $staffId = $currentUser->id;
        }

        $paymentMethod = $request->input('payment_method');
        $podStatus = $request->input('pod_status');
        $orderStatus = $request->input('status');
        $search = $request->input('search');

        // Base Query: matches orders created in date range OR orders with POD cash collected/reconciled in date range
        $query = Order::with(['customer', 'assignedStaff', 'createdByStaff', 'remittedByStaff', 'reconciledByAdmin'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                  ->orWhereBetween('pod_collected_at', [$startDate, $endDate])
                  ->orWhereBetween('pod_reconciled_at', [$startDate, $endDate]);
            });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('tracking_number', 'like', "%{$search}%")
                  ->orWhere('billing_name', 'like', "%{$search}%")
                  ->orWhere('billing_phone', 'like', "%{$search}%")
                  ->orWhere('guest_email', 'like', "%{$search}%");
            });
        }

        if ($staffId) {
            $query->where(function ($q) use ($staffId) {
                $q->where('assigned_staff_id', $staffId)
                  ->orWhere('created_by_staff_id', $staffId)
                  ->orWhere('remitted_by_staff_id', $staffId)
                  ->orWhere('reconciled_by_admin_id', $staffId);
            });
        }

        if ($paymentMethod) {
            $query->where('payment_method', $paymentMethod);
        }

        if ($podStatus) {
            $query->where('pod_status', $podStatus);
        }

        if ($orderStatus) {
            $query->where('status', $orderStatus);
        }

        // Summary Aggregates (before pagination)
        $summaryQuery = clone $query;

        $grossSalesQuery = clone $summaryQuery;
        if (!$orderStatus) {
            $grossSalesQuery->whereNotIn('status', ['pending', 'cancelled']);
        } else {
            $grossSalesQuery->whereNotIn('status', ['cancelled']);
        }
        $grossSales = (float) $grossSalesQuery->sum('total_amount');
        $totalOrdersCount = (int) (clone $summaryQuery)->count();
        $completedOrdersCount = (int) (clone $summaryQuery)->where('status', 'completed')->count();
        $discountsGiven = (float) (clone $summaryQuery)->whereNotIn('status', ['pending', 'cancelled'])->sum('discount_amount');

        // POD Floating Cash vs Reconciled Cash
        $podCashInHand = (float) (clone $summaryQuery)
            ->where('payment_method', 'pod')
            ->where('pod_status', 'cash_collected')
            ->whereNotIn('status', ['cancelled'])
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(pod_amount_collected, total_amount)'));

        $podAwaitingCollection = (float) (clone $summaryQuery)
            ->where('payment_method', 'pod')
            ->whereIn('pod_status', ['awaiting_collection', 'pending_dispatch'])
            ->whereNotIn('status', ['cancelled'])
            ->sum('total_amount');

        $podFloatingCash = (float) (clone $summaryQuery)
            ->where('payment_method', 'pod')
            ->whereIn('pod_status', ['awaiting_collection', 'pending_dispatch', 'cash_collected'])
            ->whereNotIn('status', ['cancelled'])
            ->sum('total_amount');

        $podRemittedCash = (float) (clone $summaryQuery)
            ->where('payment_method', 'pod')
            ->where('pod_status', 'remitted_to_store')
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(pod_amount_collected, total_amount)'));

        $podReconciledCash = (float) (clone $summaryQuery)
            ->where('payment_method', 'pod')
            ->where('pod_status', 'reconciled')
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(pod_amount_collected, total_amount)'));

        // Staff Performance breakdown (for Admin overview)
        $staffPerformance = [];
        if ($isAdmin) {
            $staffList = User::whereIn('role', ['staff', 'administrator', 'super administrator'])
                ->select('id', 'name', 'role')
                ->get();

            foreach ($staffList as $st) {
                $stOrders = Order::whereBetween('created_at', [$startDate, $endDate])
                    ->where(function ($q) use ($st) {
                        $q->where('assigned_staff_id', $st->id)
                          ->orWhere('created_by_staff_id', $st->id);
                    })
                    ->whereNotIn('status', ['cancelled']);

                $count = (clone $stOrders)->count();
                if ($count > 0) {
                    $salesSum = (float) (clone $stOrders)->sum('total_amount');
                    $floatingPod = (float) Order::whereBetween('created_at', [$startDate, $endDate])
                        ->where('assigned_staff_id', $st->id)
                        ->where('payment_method', 'pod')
                        ->whereIn('pod_status', ['awaiting_collection', 'cash_collected'])
                        ->sum('total_amount');

                    $staffPerformance[] = [
                        'id'           => $st->id,
                        'name'         => $st->name,
                        'role'         => $st->role,
                        'orders_count' => $count,
                        'total_sales'  => $salesSum,
                        'floating_pod' => $floatingPod,
                    ];
                }
            }
        }

        // Paginated Orders List
        $orders = $query->latest()->paginate(20)->withQueryString();

        // All active staff for the filter dropdown
        $allStaff = User::whereIn('role', ['staff', 'administrator', 'super administrator'])
            ->select('id', 'name', 'role')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Reports/Sales', [
            'isAdmin'          => $isAdmin,
            'orders'           => $orders,
            'allStaff'         => $allStaff,
            'filters'          => [
                'preset'         => $preset,
                'start_date'     => $startDate->format('Y-m-d'),
                'end_date'       => $endDate->format('Y-m-d'),
                'staff_id'       => $staffId,
                'payment_method' => $paymentMethod,
                'pod_status'     => $podStatus,
                'status'         => $orderStatus,
                'search'         => $search,
            ],
            'summary'          => [
                'gross_sales'             => $grossSales,
                'total_orders'            => $totalOrdersCount,
                'completed_orders'        => $completedOrdersCount,
                'discounts_given'         => $discountsGiven,
                'pod_cash_in_hand'        => $podCashInHand,        // Physical money with rider!
                'pod_floating_cash'       => $podFloatingCash,       // Backwards-compatible
                'pod_remitted_cash'       => $podRemittedCash,
                'pod_reconciled_cash'     => $podReconciledCash,
                'pod_awaiting_collection' => $podAwaitingCollection,
            ],
            'staffPerformance' => $staffPerformance,
        ]);
    }

    /**
     * Export the filtered sales report to CSV.
     */
    public function exportCsv(Request $request)
    {
        $currentUser = $request->user();
        $isAdmin = in_array($currentUser->role, ['administrator', 'super administrator']);

        $preset = $request->input('preset', 'today');
        [$startDate, $endDate] = $this->resolveDateRange($preset, $request->input('start_date'), $request->input('end_date'));

        $staffId = $request->input('staff_id');
        if (!$isAdmin) {
            $staffId = $currentUser->id;
        }

        $query = Order::with(['customer', 'assignedStaff', 'createdByStaff', 'reconciledByAdmin'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                  ->orWhereBetween('pod_collected_at', [$startDate, $endDate])
                  ->orWhereBetween('pod_reconciled_at', [$startDate, $endDate]);
            });

        if ($staffId) {
            $query->where(function ($q) use ($staffId) {
                $q->where('assigned_staff_id', $staffId)
                  ->orWhere('created_by_staff_id', $staffId)
                  ->orWhere('remitted_by_staff_id', $staffId)
                  ->orWhere('reconciled_by_admin_id', $staffId);
            });
        }

        if ($method = $request->input('payment_method')) {
            $query->where('payment_method', $method);
        }

        if ($pod = $request->input('pod_status')) {
            $query->where('pod_status', $pod);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $orders = $query->latest()->get();

        $filename = 'sales_report_' . $startDate->format('Ymd') . '_to_' . $endDate->format('Ymd') . '.csv';

        return new StreamedResponse(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Order ID',
                'Tracking #',
                'Date',
                'Customer',
                'Phone',
                'Total Amount (NGN)',
                'Payment Method',
                'Order Status',
                'POD Status',
                'Assigned Staff / Rider',
                'Reconciled By',
            ]);

            foreach ($orders as $o) {
                fputcsv($handle, [
                    $o->id,
                    $o->tracking_number,
                    $o->created_at ? $o->created_at->format('Y-m-d H:i') : '',
                    $o->billing_name ?: ($o->customer?->name ?? 'Guest'),
                    $o->billing_phone ?: ($o->customer?->phone ?? ''),
                    number_format((float) $o->total_amount, 2, '.', ''),
                    strtoupper($o->payment_method ?: 'N/A'),
                    ucfirst($o->status),
                    $o->pod_status ?: 'none',
                    $o->assignedStaff?->name ?: 'Unassigned',
                    $o->reconciledByAdmin?->name ?: 'Not Reconciled',
                ]);
            }
            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Assign staff/courier rider to an order.
     */
    public function assignStaff(Request $request, Order $order)
    {
        $validated = $request->validate([
            'assigned_staff_id' => 'required|exists:users,id',
        ]);

        $order->update([
            'assigned_staff_id' => $validated['assigned_staff_id'],
            'pod_status'        => $order->payment_method === 'pod' && in_array($order->pod_status, ['none', 'pending_dispatch'])
                ? 'awaiting_collection'
                : $order->pod_status,
        ]);

        $assignedUser = User::find($validated['assigned_staff_id']);
        $assignedName = $assignedUser ? $assignedUser->name : "Staff #{$validated['assigned_staff_id']}";
        $order->recordStatusHistory(
            toStatus: $order->status,
            fromStatus: $order->status,
            event: 'staff_assigned',
            comment: "Order assigned to courier / staff: {$assignedName}.",
            metadata: ['assigned_staff_id' => $validated['assigned_staff_id']]
        );

        return back()->with('success', "Order #{$order->id} successfully assigned to staff.");
    }

    /**
     * Rider/Staff marks POD cash collected from customer.
     */
    public function markCashCollected(Request $request, Order $order)
    {
        if ($order->status === 'cancelled') {
            return back()->with('error', 'Cannot record payment collection for a cancelled order.');
        }

        $collected = (float) $request->input('amount', $order->total_amount);

        if ($collected < (float) $order->total_amount) {
            return back()->withErrors([
                'amount' => "The collected amount (₦" . number_format($collected, 2) . ") cannot be less than the order total (₦" . number_format($order->total_amount, 2) . "). Full payment is required for Payment on Delivery."
            ])->with('error', "Cannot accept a lesser amount. Full payment of ₦" . number_format($order->total_amount, 2) . " is required.");
        }

        $channel = $request->input('channel', 'cash');
        $channelLabel = match ($channel) {
            'bank_transfer' => 'Instant Bank Transfer',
            'pos_terminal'  => 'Handheld POS Terminal',
            default         => 'Physical Cash',
        };

        $order->update([
            'pod_status'              => 'cash_collected',
            'pod_amount_collected'    => $collected > 0 ? $collected : $order->total_amount,
            'pod_collected_at'        => now(),
            'remitted_by_staff_id'    => $request->user()->id,
            'pod_reconciliation_note' => "Collected via {$channelLabel}",
        ]);

        $order->recordStatusHistory(
            toStatus: $order->status,
            fromStatus: $order->status,
            event: 'payment_collected',
            comment: "Doorstep payment of ₦" . number_format($order->pod_amount_collected, 2) . " collected via {$channelLabel}.",
            metadata: [
                'amount'        => $order->pod_amount_collected,
                'channel'       => $channel,
                'channel_label' => $channelLabel,
            ]
        );

        return back()->with('success', "POD payment of ₦" . number_format($order->pod_amount_collected, 2) . " ({$channelLabel}) marked collected for Order #{$order->id}. Please remit to cashier.");
    }

    /**
     * Mark POD cash remitted & auto-reconciled to store cashier/desk.
     * Strictly restricted to cashiers/administrators receiving the physical money.
     */
    public function markCashRemitted(Request $request, Order $order)
    {
        if ($order->status === 'cancelled') {
            return back()->with('error', 'Cannot remit cash for a cancelled order.');
        }

        $currentUser = $request->user();
        $canReconcile = $currentUser->can('pod.reconcile') || in_array($currentUser->role, ['administrator', 'super administrator'], true);
        if (!$canReconcile) {
            abort(403, 'Unauthorized. Delivery riders cannot remit or reconcile cash. Only an authorized store cashier or administrator can receive and confirm remittance handover.');
        }

        $reconcilingAdminId = $currentUser->id;
        $auditorName = $currentUser->name;

        $oldStatus = $order->status;

        $order->update([
            'pod_status'              => 'reconciled',
            'pod_amount_collected'    => $order->pod_amount_collected ?: $order->total_amount,
            'pod_remitted_at'         => now(),
            'remitted_by_staff_id'    => $order->assigned_staff_id ?: $currentUser->id,
            'pod_reconciled_at'       => now(),
            'reconciled_by_admin_id'  => $reconcilingAdminId,
            'pod_reconciliation_note' => $request->input('note') ?: "Auto-reconciled on remittance by {$auditorName}.",
            'status'                  => 'completed',
        ]);

        $statusNote = $oldStatus !== 'completed'
            ? "Physical cash remittance received & reconciled into store till under {$auditorName}. Order status marked Completed."
            : "Physical cash remittance received & reconciled into store till under {$auditorName}. Order Completed.";

        $order->recordStatusHistory(
            toStatus: 'completed',
            fromStatus: $oldStatus,
            event: 'reconciled',
            comment: $statusNote,
            metadata: ['amount' => $order->pod_amount_collected, 'reconciled_by' => $auditorName]
        );

        return back()->with('success', "Order #{$order->id} cash (₦" . number_format($order->pod_amount_collected, 2) . ") remitted & reconciled under {$auditorName}'s audit profile.");
    }

    /**
     * Admin confirms and reconciles POD cash in hand.
     */
    public function reconcilePod(Request $request, Order $order)
    {
        if ($order->status === 'cancelled') {
            return back()->with('error', 'Cannot reconcile cash for a cancelled order.');
        }

        $currentUser = $request->user();
        if (!$currentUser->isSuperAdmin() && !in_array($currentUser->role, ['administrator', 'super administrator'], true) && !$currentUser->can('pod.reconcile')) {
            abort(403, 'You do not have permission to reconcile POD cash balances.');
        }

        $validated = $request->validate([
            'reconciliation_note' => 'nullable|string|max:500',
        ]);

        $order->update([
            'pod_status'              => 'reconciled',
            'pod_amount_collected'    => $order->pod_amount_collected ?: $order->total_amount,
            'pod_reconciled_at'       => now(),
            'reconciled_by_admin_id'  => $currentUser->id,
            'pod_reconciliation_note' => $validated['reconciliation_note'] ?? null,
            'status'                  => 'completed', // Finalizes the order
        ]);

        return back()->with('success', "Order #{$order->id} POD cash of ₦" . number_format($order->total_amount, 2) . " successfully verified and reconciled.");
    }

    /**
     * Resolve date range from preset or custom input.
     */
    protected function resolveDateRange(string $preset, ?string $start, ?string $end): array
    {
        $now = Carbon::now();

        return match ($preset) {
            'yesterday' => [
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
            ],
            'this_week' => [
                $now->copy()->startOfWeek()->startOfDay(),
                $now->copy()->endOfWeek()->endOfDay(),
            ],
            'this_month' => [
                $now->copy()->startOfMonth()->startOfDay(),
                $now->copy()->endOfMonth()->endOfDay(),
            ],
            'custom' => [
                $start ? Carbon::parse($start)->startOfDay() : $now->copy()->startOfDay(),
                $end ? Carbon::parse($end)->endOfDay() : $now->copy()->endOfDay(),
            ],
            default => [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
            ],
        };
    }
}

