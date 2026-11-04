<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleEnums;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PodCashController extends Controller
{
    /**
     * Dedicated POD Cash Management Dashboard & Reconciliation Hub.
     */
    public function index(Request $request)
    {
        $currentUser = $request->user();

        // Permissions check
        $canReconcile = $currentUser->isSuperAdmin()
            || in_array($currentUser->role, [RoleEnums::Administrator->value, RoleEnums::SuperAdministrator->value], true)
            || $currentUser->can('pod.reconcile');

        $canViewAll = $currentUser->isSuperAdmin()
            || in_array($currentUser->role, [RoleEnums::Administrator->value, RoleEnums::SuperAdministrator->value], true)
            || $currentUser->can('pod.reconcile')
            || $currentUser->can('orders.view_all');

        $isRider = !$canViewAll;

        // Active Tab: 'in_hand', 'remitted', 'reconciled', 'awaiting', 'all'
        $tab = $request->input('tab', 'in_hand');

        // Filter by rider/staff
        $riderId = $request->input('rider_id');
        if (!$canViewAll) {
            $riderId = $currentUser->id;
        }

        $search = $request->input('search');
        $cashierId = $request->input('cashier_id');
        $datePreset = $request->input('date_preset', 'all'); // 'today', 'this_week', 'this_month', 'all'

        // ── Base Summary Query (Respects rider restriction if non-admin) ──
        $summaryBase = Order::query()
            ->where('payment_method', 'pod')
            ->whereNotIn('status', ['cancelled']);

        if (!$canViewAll || $riderId) {
            $targetStaffId = $riderId ?: $currentUser->id;
            $summaryBase->where(function ($q) use ($targetStaffId) {
                $q->where('assigned_staff_id', $targetStaffId)
                  ->orWhere('remitted_by_staff_id', $targetStaffId);
            });
        }

        if ($cashierId) {
            $summaryBase->where('reconciled_by_admin_id', $cashierId);
        }

        // Summary KPI Metrics
        $inHandCash = (float) (clone $summaryBase)
            ->where('pod_status', 'cash_collected')
            ->sum(DB::raw('COALESCE(pod_amount_collected, total_amount)'));
        $inHandCount = (int) (clone $summaryBase)
            ->where('pod_status', 'cash_collected')
            ->count();

        $remittedCash = (float) (clone $summaryBase)
            ->where('pod_status', 'remitted_to_store')
            ->sum(DB::raw('COALESCE(pod_amount_collected, total_amount)'));
        $remittedCount = (int) (clone $summaryBase)
            ->where('pod_status', 'remitted_to_store')
            ->count();

        $reconciledCash = (float) (clone $summaryBase)
            ->where('pod_status', 'reconciled')
            ->sum(DB::raw('COALESCE(pod_amount_collected, total_amount)'));
        $reconciledCount = (int) (clone $summaryBase)
            ->where('pod_status', 'reconciled')
            ->count();

        // 4. In Transit (Assigned to rider and active in delivery transit)
        $inTransitCash = (float) (clone $summaryBase)
            ->where('pod_status', 'awaiting_collection')
            ->whereNotNull('assigned_staff_id')
            ->sum('total_amount');
        $inTransitCount = (int) (clone $summaryBase)
            ->where('pod_status', 'awaiting_collection')
            ->whereNotNull('assigned_staff_id')
            ->count();

        // 5. Pending Dispatch (In store/warehouse: unassigned or waiting for rider pickup)
        $pendingDispatchCash = (float) (clone $summaryBase)
            ->where(function ($q) {
                $q->where('pod_status', 'pending_dispatch')
                  ->orWhere(function ($sub) {
                      $sub->where('pod_status', 'awaiting_collection')
                          ->whereNull('assigned_staff_id');
                  });
            })
            ->sum('total_amount');
        $pendingDispatchCount = (int) (clone $summaryBase)
            ->where(function ($q) {
                $q->where('pod_status', 'pending_dispatch')
                  ->orWhere(function ($sub) {
                      $sub->where('pod_status', 'awaiting_collection')
                          ->whereNull('assigned_staff_id');
                  });
            })
            ->count();

        $awaitingCash = $inTransitCash + $pendingDispatchCash;
        $awaitingCount = $inTransitCount + $pendingDispatchCount;

        // ── Active Rider Float Monitor (Admins & Cashiers Only) ────────
        $riderFloats = [];
        if ($canViewAll) {
            $floatsRaw = Order::select('assigned_staff_id',
                    DB::raw('COUNT(*) as orders_count'),
                    DB::raw('SUM(COALESCE(pod_amount_collected, total_amount)) as total_float')
                )
                ->where('payment_method', 'pod')
                ->where('pod_status', 'cash_collected')
                ->whereNotNull('assigned_staff_id')
                ->groupBy('assigned_staff_id')
                ->get();

            $staffIds = $floatsRaw->pluck('assigned_staff_id');
            $staffMembers = User::whereIn('id', $staffIds)->select('id', 'name', 'email')->get()->keyBy('id');

            foreach ($floatsRaw as $item) {
                $st = $staffMembers->get($item->assigned_staff_id);
                if ($st) {
                    $riderFloats[] = [
                        'staff_id'     => $st->id,
                        'name'         => $st->name,
                        'email'        => $st->email,
                        'orders_count' => (int) $item->orders_count,
                        'total_float'  => (float) $item->total_float,
                    ];
                }
            }
        }

        // ── Cashier / Admin Settlement Audit Breakdown (Audited & Reconciled) ──
        $cashierAudits = [];
        if ($canViewAll) {
            $cashiersRaw = Order::select('reconciled_by_admin_id',
                    DB::raw('COUNT(*) as orders_count'),
                    DB::raw('SUM(COALESCE(pod_amount_collected, total_amount)) as total_reconciled')
                )
                ->where('payment_method', 'pod')
                ->where('pod_status', 'reconciled')
                ->whereNotNull('reconciled_by_admin_id')
                ->groupBy('reconciled_by_admin_id')
                ->get();

            $adminIds = $cashiersRaw->pluck('reconciled_by_admin_id');
            $adminUsers = User::whereIn('id', $adminIds)->select('id', 'name', 'email', 'role')->get()->keyBy('id');

            foreach ($cashiersRaw as $item) {
                $ad = $adminUsers->get($item->reconciled_by_admin_id);
                if ($ad) {
                    $cashierAudits[] = [
                        'admin_id'         => $ad->id,
                        'name'             => $ad->name,
                        'email'            => $ad->email,
                        'role'             => $ad->role,
                        'orders_count'     => (int) $item->orders_count,
                        'total_reconciled' => (float) $item->total_reconciled,
                    ];
                }
            }
        }

        // ── Main Paginated Table Query ────────────────────────────────
        $tableQuery = Order::query()
            ->with(['customer', 'assignedStaff', 'remittedByStaff', 'reconciledByAdmin'])
            ->where('payment_method', 'pod')
            ->whereNotIn('status', ['cancelled']);

        if (!$canViewAll || $riderId) {
            $targetStaffId = $riderId ?: $currentUser->id;
            $tableQuery->where(function ($q) use ($targetStaffId) {
                $q->where('assigned_staff_id', $targetStaffId)
                  ->orWhere('remitted_by_staff_id', $targetStaffId);
            });
        }

        if ($cashierId) {
            $tableQuery->where('reconciled_by_admin_id', $cashierId);
        }

        // Filter by tab
        match ($tab) {
            'in_hand'          => $tableQuery->where('pod_status', 'cash_collected'),
            'remitted'         => $tableQuery->where('pod_status', 'remitted_to_store'),
            'reconciled'       => $tableQuery->where('pod_status', 'reconciled'),
            'in_transit'       => $tableQuery->where('pod_status', 'awaiting_collection')->whereNotNull('assigned_staff_id'),
            'pending_dispatch' => $tableQuery->where(function ($q) {
                $q->where('pod_status', 'pending_dispatch')
                  ->orWhere(function ($sub) {
                      $sub->where('pod_status', 'awaiting_collection')
                          ->whereNull('assigned_staff_id');
                  });
            }),
            'awaiting'         => $tableQuery->whereIn('pod_status', ['pending_dispatch', 'awaiting_collection']),
            default            => null, // 'all' shows all records
        };

        // Search
        if ($search) {
            $tableQuery->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('tracking_number', 'like', "%{$search}%")
                  ->orWhere('billing_name', 'like', "%{$search}%")
                  ->orWhere('billing_phone', 'like', "%{$search}%")
                  ->orWhere('guest_email', 'like', "%{$search}%");
            });
        }

        // Date Preset Filter
        if ($datePreset === 'today') {
            $tableQuery->whereDate('created_at', Carbon::today());
        } elseif ($datePreset === 'this_week') {
            $tableQuery->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        } elseif ($datePreset === 'this_month') {
            $tableQuery->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
        }

        $orders = $tableQuery->latest('updated_at')->paginate(20)->withQueryString();

        // Staff list for filter dropdown (Admins/Cashiers)
        $allRiders = [];
        if ($canViewAll) {
            $allRiders = User::whereIn('role', ['staff', 'administrator', 'super administrator'])
                ->select('id', 'name', 'role')
                ->orderBy('name')
                ->get();
        }

        return Inertia::render('Admin/PodCash/Index', [
            'canReconcile' => $canReconcile,
            'canViewAll'   => $canViewAll,
            'isRider'      => $isRider,
            'tab'          => $tab,
            'filters'      => [
                'rider_id'    => $riderId,
                'cashier_id'  => $cashierId,
                'search'      => $search,
                'date_preset' => $datePreset,
            ],
            'kpis'         => [
                'in_hand_cash'           => $inHandCash,
                'in_hand_count'          => $inHandCount,
                'remitted_cash'          => $remittedCash,
                'remitted_count'         => $remittedCount,
                'reconciled_cash'        => $reconciledCash,
                'reconciled_count'       => $reconciledCount,
                'in_transit_cash'        => $inTransitCash,
                'in_transit_count'       => $inTransitCount,
                'pending_dispatch_cash'  => $pendingDispatchCash,
                'pending_dispatch_count' => $pendingDispatchCount,
                'awaiting_cash'          => $awaitingCash,
                'awaiting_count'         => $awaitingCount,
            ],
            'riderFloats'   => $riderFloats,
            'cashierAudits' => $cashierAudits,
            'allRiders'     => $allRiders,
            'orders'        => $orders,
        ]);
    }

    /**
     * Record cash or transfer collected from customer at doorstep.
     */
    public function markCollected(Request $request, Order $order)
    {
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

        return back()->with('success', "Payment of ₦" . number_format($order->pod_amount_collected, 2) . " ({$channelLabel}) recorded for Order #{$order->id}. Please remit to store.");
    }

    /**
     * Record physical cash handover from rider to store cashier (Auto-reconciles directly).
     * Strictly restricted to cashiers/administrators receiving the physical money.
     */
    public function markRemitted(Request $request, Order $order)
    {
        $currentUser = $request->user();

        // Determine receiving cashier/admin for profile audit
        $canReconcile = $currentUser->can('pod.reconcile') || in_array($currentUser->role, ['administrator', 'super administrator'], true);
        if (!$canReconcile) {
            abort(403, 'Unauthorized. Delivery riders cannot remit or reconcile cash. Only an authorized store cashier or administrator can receive and confirm remittance handover.');
        }

        $reconcilingAdminId = $currentUser->id;
        $auditorName = $currentUser->name;
        $note = $request->input('note') ?: "Auto-reconciled on store handover under {$auditorName}.";

        $oldStatus = $order->status;

        $order->update([
            'pod_status'              => 'reconciled',
            'pod_amount_collected'    => $order->pod_amount_collected ?: $order->total_amount,
            'pod_remitted_at'         => now(),
            'remitted_by_staff_id'    => $order->assigned_staff_id ?: $currentUser->id,
            'pod_reconciled_at'       => now(),
            'reconciled_by_admin_id'  => $reconcilingAdminId,
            'pod_reconciliation_note' => $note,
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

        return back()->with('success', "Order #{$order->id} (₦" . number_format($order->pod_amount_collected, 2) . ") remitted & reconciled under {$auditorName}'s audit profile.");
    }

    /**
     * Bulk remit all or selected orders currently in hand (Auto-reconciles directly).
     * Strictly restricted to cashiers/administrators receiving the physical money.
     */
    public function bulkRemit(Request $request)
    {
        $currentUser = $request->user();
        $canReconcile = $currentUser->can('pod.reconcile') || in_array($currentUser->role, ['administrator', 'super administrator'], true);
        if (!$canReconcile) {
            abort(403, 'Unauthorized. Delivery riders cannot remit or reconcile cash. Only an authorized store cashier or administrator can receive and confirm remittance handover.');
        }

        $orderIds = $request->input('order_ids', []);

        if (empty($orderIds)) {
            return back()->with('error', 'No orders selected for remittance.');
        }

        $orders = Order::whereIn('id', $orderIds)
            ->where('payment_method', 'pod')
            ->whereIn('pod_status', ['cash_collected', 'remitted_to_store']);

        $count = $orders->count();
        $totalAmount = (float) (clone $orders)->sum(DB::raw('COALESCE(pod_amount_collected, total_amount)'));

        $reconcilingAdminId = $currentUser->id;
        $auditorName = $currentUser->name;
        $note = $request->input('reconciliation_note') ?: "Bulk auto-reconciled on store handover under {$auditorName}.";

        $orderModels = $orders->get();
        $orders->update([
            'pod_status'              => 'reconciled',
            'pod_remitted_at'         => now(),
            'pod_reconciled_at'       => now(),
            'reconciled_by_admin_id'  => $reconcilingAdminId,
            'pod_reconciliation_note' => $note,
            'status'                  => 'completed',
        ]);

        foreach ($orderModels as $ord) {
            $ord->recordStatusHistory(
                toStatus: 'completed',
                fromStatus: $ord->status,
                event: 'reconciled',
                comment: "Bulk cash remittance received & reconciled into store till under {$auditorName}. Order marked Completed.",
                metadata: ['reconciled_by' => $auditorName]
            );
        }

        return back()->with('success', "Successfully remitted & reconciled {$count} orders totaling ₦" . number_format($totalAmount, 2) . " under {$auditorName}'s audit profile.");
    }

    /**
     * Cashier / Store Admin verifies and reconciles cash deposit into cash drawer/till.
     */
    public function reconcile(Request $request, Order $order)
    {
        $currentUser = $request->user();

        $canReconcile = $currentUser->isSuperAdmin()
            || in_array($currentUser->role, [RoleEnums::Administrator->value, RoleEnums::SuperAdministrator->value], true)
            || $currentUser->can('pod.reconcile');

        if (!$canReconcile) {
            abort(403, 'Unauthorized. Only store cashiers and administrators can reconcile cash into the till.');
        }

        $order->update([
            'pod_status'               => 'reconciled',
            'pod_amount_collected'     => $order->pod_amount_collected ?: $order->total_amount,
            'pod_reconciled_at'        => now(),
            'reconciled_by_admin_id'   => $currentUser->id,
            'pod_reconciliation_note'  => $request->input('reconciliation_note', 'Verified & deposited in store cash drawer.'),
        ]);

        return back()->with('success', "Order #{$order->id} (₦" . number_format($order->pod_amount_collected, 2) . ") successfully reconciled into cash drawer.");
    }

    /**
     * Bulk reconcile multiple remitted orders at once (e.g. End of Shift reconciliation).
     */
    public function bulkReconcile(Request $request)
    {
        $currentUser = $request->user();

        $canReconcile = $currentUser->isSuperAdmin()
            || in_array($currentUser->role, [RoleEnums::Administrator->value, RoleEnums::SuperAdministrator->value], true)
            || $currentUser->can('pod.reconcile');

        if (!$canReconcile) {
            abort(403, 'Unauthorized to reconcile cash.');
        }

        $orderIds = $request->input('order_ids', []);
        $note = $request->input('reconciliation_note', 'Bulk shift reconciliation deposited into cash drawer.');

        if (empty($orderIds)) {
            return back()->with('error', 'No orders selected for reconciliation.');
        }

        $orders = Order::whereIn('id', $orderIds)
            ->where('payment_method', 'pod')
            ->whereIn('pod_status', ['remitted_to_store', 'cash_collected']);

        $count = $orders->count();
        $totalAmount = (float) (clone $orders)->sum(DB::raw('COALESCE(pod_amount_collected, total_amount)'));

        $orders->update([
            'pod_status'              => 'reconciled',
            'pod_reconciled_at'       => now(),
            'reconciled_by_admin_id'  => $currentUser->id,
            'pod_reconciliation_note' => $note,
        ]);

        return back()->with('success', "Audited and reconciled {$count} orders totaling ₦" . number_format($totalAmount, 2) . " into store cash drawer.");
    }

    /**
     * Export POD cash audit trail to CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $currentUser = $request->user();
        $canViewAll = $currentUser->isSuperAdmin()
            || in_array($currentUser->role, [RoleEnums::Administrator->value, RoleEnums::SuperAdministrator->value], true)
            || $currentUser->can('pod.reconcile')
            || $currentUser->can('orders.view_all');

        $query = Order::query()
            ->with(['assignedStaff', 'remittedByStaff', 'reconciledByAdmin', 'customer'])
            ->where('payment_method', 'pod')
            ->whereNotIn('status', ['cancelled']);

        if (!$canViewAll || ($riderId = $request->input('rider_id'))) {
            $targetStaffId = $riderId ?: $currentUser->id;
            $query->where(function ($q) use ($targetStaffId) {
                $q->where('assigned_staff_id', $targetStaffId)
                  ->orWhere('remitted_by_staff_id', $targetStaffId);
            });
        }

        if ($cashierId = $request->input('cashier_id')) {
            $query->where('reconciled_by_admin_id', $cashierId);
        }

        if ($tab = $request->input('tab')) {
            match ($tab) {
                'in_hand'    => $query->where('pod_status', 'cash_collected'),
                'remitted'   => $query->where('pod_status', 'remitted_to_store'),
                'reconciled' => $query->where('pod_status', 'reconciled'),
                'awaiting'   => $query->where('pod_status', 'awaiting_collection'),
                default      => null,
            };
        }

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="pod_cash_report_' . date('Y-m-d_His') . '.csv"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                'Order ID',
                'Tracking Number',
                'Date',
                'Customer Name',
                'Customer Phone',
                'Order Total (NGN)',
                'Collected Amount (NGN)',
                'POD Cash Status',
                'Assigned Rider / Staff',
                'Collected At',
                'Remitted At',
                'Reconciled At',
                'Reconciled By',
                'Reconciliation Note',
            ]);

            $query->chunk(200, function ($orders) use ($handle) {
                foreach ($orders as $o) {
                    fputcsv($handle, [
                        $o->id,
                        $o->tracking_number,
                        $o->created_at ? $o->created_at->format('Y-m-d H:i') : '',
                        $o->billing_name ?: ($o->customer?->name ?: $o->guest_email),
                        $o->billing_phone ?: ($o->customer?->phone ?: ''),
                        number_format($o->total_amount, 2, '.', ''),
                        number_format($o->pod_amount_collected ?: $o->total_amount, 2, '.', ''),
                        strtoupper(str_replace('_', ' ', $o->pod_status ?: 'none')),
                        $o->assignedStaff?->name ?: ($o->remittedByStaff?->name ?: 'Unassigned'),
                        $o->pod_collected_at ? Carbon::parse($o->pod_collected_at)->format('Y-m-d H:i') : '',
                        $o->pod_remitted_at ? Carbon::parse($o->pod_remitted_at)->format('Y-m-d H:i') : '',
                        $o->pod_reconciled_at ? Carbon::parse($o->pod_reconciled_at)->format('Y-m-d H:i') : '',
                        $o->reconciledByAdmin?->name ?: 'Not Reconciled',
                        $o->pod_reconciliation_note ?: '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}

