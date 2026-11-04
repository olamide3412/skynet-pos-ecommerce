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

class CollectionsReceivedController extends Controller
{
    /**
     * Dedicated Audit & Settlement Hub for Collections Received by Cashiers & Admins.
     */
    public function index(Request $request)
    {
        $currentUser = $request->user();

        // Access check
        $canViewAll = $currentUser->isSuperAdmin()
            || in_array($currentUser->role, [RoleEnums::Administrator->value, RoleEnums::SuperAdministrator->value], true)
            || $currentUser->can('orders.view_all')
            || $currentUser->can('pod.reconcile');

        if (!$canViewAll && !$currentUser->can('pod.reconcile')) {
            abort(403, 'Unauthorized. Access restricted to store cashiers, accountants, and administrators.');
        }

        // Date range handling (Filter by pod_reconciled_at)
        $preset = $request->input('preset', 'today'); // 'today', 'yesterday', 'this_week', 'this_month', 'custom', 'all'
        [$startDate, $endDate] = $this->resolveDateRange($preset, $request->input('start_date'), $request->input('end_date'));

        $cashierId = $request->input('cashier_id');
        if (!$canViewAll) {
            $cashierId = $currentUser->id;
        }

        $riderId = $request->input('rider_id');
        $channel = $request->input('channel', 'all');
        $search = $request->input('search');

        // ── Base Query for Reconciled / Received Collections ──
        $baseQuery = Order::query()
            ->where('payment_method', 'pod')
            ->where('pod_status', 'reconciled')
            ->whereNotNull('pod_reconciled_at')
            ->whereNotIn('status', ['cancelled']);

        if ($startDate && $endDate) {
            $baseQuery->whereBetween('pod_reconciled_at', [$startDate, $endDate]);
        }

        if ($cashierId) {
            $baseQuery->where('reconciled_by_admin_id', $cashierId);
        }

        if ($riderId) {
            $baseQuery->where(function ($q) use ($riderId) {
                $q->where('assigned_staff_id', $riderId)
                  ->orWhere('remitted_by_staff_id', $riderId);
            });
        }

        if ($channel !== 'all') {
            if ($channel === 'bank_transfer') {
                $baseQuery->where('pod_reconciliation_note', 'like', '%transfer%');
            } elseif ($channel === 'pos_terminal') {
                $baseQuery->where(function ($q) {
                    $q->where('pod_reconciliation_note', 'like', '%pos%')
                      ->orWhere('pod_reconciliation_note', 'like', '%terminal%');
                });
            } elseif ($channel === 'cash') {
                $baseQuery->where(function ($q) {
                    $q->where('pod_reconciliation_note', 'like', '%cash%')
                      ->orWhere(function ($sub) {
                          $sub->whereNull('pod_reconciliation_note')
                              ->orWhere(function ($noteQ) {
                                  $noteQ->where('pod_reconciliation_note', 'not like', '%transfer%')
                                        ->where('pod_reconciliation_note', 'not like', '%pos%');
                              });
                      });
                });
            }
        }

        // Summary KPIs for current filter selection
        $totalReceived = (float) (clone $baseQuery)->sum(DB::raw('COALESCE(pod_amount_collected, total_amount)'));
        $ordersCount   = (int) (clone $baseQuery)->count();

        // Estimated channel breakdown
        $transferTotal = (float) (clone $baseQuery)->where('pod_reconciliation_note', 'like', '%transfer%')
            ->sum(DB::raw('COALESCE(pod_amount_collected, total_amount)'));
        $posTotal = (float) (clone $baseQuery)->where(function ($q) {
            $q->where('pod_reconciliation_note', 'like', '%pos%')
              ->orWhere('pod_reconciliation_note', 'like', '%terminal%');
        })->sum(DB::raw('COALESCE(pod_amount_collected, total_amount)'));
        $cashTotal = max(0, $totalReceived - ($transferTotal + $posTotal));

        // ── Cashier / Auditor Breakdown ──
        $cashierBreakdown = [];
        if ($canViewAll) {
            $cashiersQuery = Order::query()
                ->select(
                    'reconciled_by_admin_id',
                    DB::raw('COUNT(*) as orders_count'),
                    DB::raw('SUM(COALESCE(pod_amount_collected, total_amount)) as total_amount')
                )
                ->where('payment_method', 'pod')
                ->where('pod_status', 'reconciled')
                ->whereNotNull('pod_reconciled_at')
                ->whereNotNull('reconciled_by_admin_id')
                ->whereNotIn('status', ['cancelled']);

            if ($startDate && $endDate) {
                $cashiersQuery->whereBetween('pod_reconciled_at', [$startDate, $endDate]);
            }

            $cashierStats = $cashiersQuery->groupBy('reconciled_by_admin_id')->get();
            $adminIds = $cashierStats->pluck('reconciled_by_admin_id');
            $adminUsers = User::whereIn('id', $adminIds)->select('id', 'name', 'email', 'role')->get()->keyBy('id');

            foreach ($cashierStats as $stat) {
                $user = $adminUsers->get($stat->reconciled_by_admin_id);
                if ($user) {
                    $cashierBreakdown[] = [
                        'id'           => $user->id,
                        'name'         => $user->name,
                        'email'        => $user->email,
                        'role'         => $user->role,
                        'orders_count' => (int) $stat->orders_count,
                        'total_amount' => (float) $stat->total_amount,
                    ];
                }
            }
        }

        // ── Main Paginated Audit Table ──
        $tableQuery = (clone $baseQuery)->with(['customer', 'assignedStaff', 'remittedByStaff', 'reconciledByAdmin']);

        if ($search) {
            $tableQuery->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('tracking_number', 'like', "%{$search}%")
                  ->orWhere('billing_name', 'like', "%{$search}%")
                  ->orWhere('billing_phone', 'like', "%{$search}%")
                  ->orWhere('guest_email', 'like', "%{$search}%")
                  ->orWhere('pod_reconciliation_note', 'like', "%{$search}%");
            });
        }

        $orders = $tableQuery->orderByDesc('pod_reconciled_at')->paginate(25)->withQueryString();

        // Staff & Cashiers for Filter Dropdowns
        $allCashiers = User::whereIn('role', ['administrator', 'super administrator', 'staff'])
            ->select('id', 'name', 'role')
            ->orderBy('name')
            ->get();

        $allRiders = User::whereIn('role', ['staff', 'administrator', 'super administrator'])
            ->select('id', 'name', 'role')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/CollectionsReceived/Index', [
            'canViewAll'       => $canViewAll,
            'filters'          => [
                'preset'     => $preset,
                'start_date' => $startDate ? $startDate->format('Y-m-d') : null,
                'end_date'   => $endDate ? $endDate->format('Y-m-d') : null,
                'cashier_id' => $cashierId,
                'rider_id'   => $riderId,
                'channel'    => $channel,
                'search'     => $search,
            ],
            'kpis'             => [
                'total_received'  => $totalReceived,
                'orders_count'    => $ordersCount,
                'cash_amount'     => $cashTotal,
                'transfer_amount' => $transferTotal,
                'pos_amount'      => $posTotal,
            ],
            'cashierBreakdown' => $cashierBreakdown,
            'allCashiers'      => $allCashiers,
            'allRiders'        => $allRiders,
            'orders'           => $orders,
        ]);
    }

    /**
     * Export Collections Received Audit Trail to CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $currentUser = $request->user();
        $canViewAll = $currentUser->isSuperAdmin()
            || in_array($currentUser->role, [RoleEnums::Administrator->value, RoleEnums::SuperAdministrator->value], true)
            || $currentUser->can('orders.view_all')
            || $currentUser->can('pod.reconcile');

        $preset = $request->input('preset', 'today');
        [$startDate, $endDate] = $this->resolveDateRange($preset, $request->input('start_date'), $request->input('end_date'));

        $cashierId = $request->input('cashier_id');
        if (!$canViewAll) {
            $cashierId = $currentUser->id;
        }

        $riderId = $request->input('rider_id');
        $channel = $request->input('channel', 'all');
        $search = $request->input('search');

        $query = Order::query()
            ->with(['assignedStaff', 'remittedByStaff', 'reconciledByAdmin', 'customer'])
            ->where('payment_method', 'pod')
            ->where('pod_status', 'reconciled')
            ->whereNotNull('pod_reconciled_at')
            ->whereNotIn('status', ['cancelled']);

        if ($startDate && $endDate) {
            $query->whereBetween('pod_reconciled_at', [$startDate, $endDate]);
        }

        if ($cashierId) {
            $query->where('reconciled_by_admin_id', $cashierId);
        }

        if ($riderId) {
            $query->where(function ($q) use ($riderId) {
                $q->where('assigned_staff_id', $riderId)
                  ->orWhere('remitted_by_staff_id', $riderId);
            });
        }

        if ($channel !== 'all') {
            if ($channel === 'bank_transfer') {
                $query->where('pod_reconciliation_note', 'like', '%transfer%');
            } elseif ($channel === 'pos_terminal') {
                $query->where(function ($q) {
                    $q->where('pod_reconciliation_note', 'like', '%pos%')
                      ->orWhere('pod_reconciliation_note', 'like', '%terminal%');
                });
            } elseif ($channel === 'cash') {
                $query->where(function ($q) {
                    $q->where('pod_reconciliation_note', 'like', '%cash%')
                      ->orWhere(function ($sub) {
                          $sub->whereNull('pod_reconciliation_note')
                              ->orWhere(function ($noteQ) {
                                  $noteQ->where('pod_reconciliation_note', 'not like', '%transfer%')
                                        ->where('pod_reconciliation_note', 'not like', '%pos%');
                              });
                      });
                });
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('tracking_number', 'like', "%{$search}%")
                  ->orWhere('billing_name', 'like', "%{$search}%")
                  ->orWhere('billing_phone', 'like', "%{$search}%")
                  ->orWhere('guest_email', 'like', "%{$search}%")
                  ->orWhere('pod_reconciliation_note', 'like', "%{$search}%");
            });
        }

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="collections_received_' . date('Y-m-d_His') . '.csv"',
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
                'Audited / Received At',
                'Order Placed Date',
                'Customer Name',
                'Customer Phone',
                'Order Total (NGN)',
                'Collected Amount (NGN)',
                'Received / Audited By',
                'Courier / Rider',
                'Audit Note',
            ]);

            $query->orderByDesc('pod_reconciled_at')->chunk(200, function ($orders) use ($handle) {
                foreach ($orders as $o) {
                    fputcsv($handle, [
                        $o->id,
                        $o->tracking_number,
                        $o->pod_reconciled_at ? $o->pod_reconciled_at->format('Y-m-d H:i:s') : '',
                        $o->created_at ? $o->created_at->format('Y-m-d H:i') : '',
                        $o->billing_name ?: ($o->customer?->name ?: $o->guest_email),
                        $o->billing_phone ?: ($o->customer?->phone ?: ''),
                        number_format($o->total_amount, 2, '.', ''),
                        number_format($o->pod_amount_collected ?: $o->total_amount, 2, '.', ''),
                        $o->reconciledByAdmin?->name ?: 'Store Cashier',
                        $o->assignedStaff?->name ?: ($o->remittedByStaff?->name ?: 'Unassigned'),
                        $o->pod_reconciliation_note ?: 'Direct Cash Drawer Settle',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Resolve start and end dates from preset or custom input.
     */
    protected function resolveDateRange(string $preset, ?string $start, ?string $end): array
    {
        if ($preset === 'custom' && $start && $end) {
            return [
                Carbon::parse($start)->startOfDay(),
                Carbon::parse($end)->endOfDay(),
            ];
        }

        return match ($preset) {
            'today'      => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
            'yesterday'  => [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()],
            'this_week'  => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'this_month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            'last_month' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
            'all'        => [null, null],
            default      => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
        };
    }
}

