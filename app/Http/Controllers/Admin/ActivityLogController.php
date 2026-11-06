<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    /**
     * Enforce strict Super Administrator access.
     */
    protected function authorizeSuperAdmin(): void
    {
        $user = auth('web')->user();
        if (!$user || $user->role !== 'super administrator') {
            abort(403, 'Unauthorized. Activity logs are restricted strictly to Super Administrators.');
        }
    }

    /**
     * Display searchable, filterable activity logs.
     */
    public function index(Request $request)
    {
        $this->authorizeSuperAdmin();

        $actor = $request->input('actor');
        $actionType = $request->input('action_type');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $search = $request->input('search');

        $query = ActivityLog::with('user:id,name,email,role')
            ->forActor($actor)
            ->forActionType($actionType)
            ->dateRange($startDate, $endDate)
            ->search($search)
            ->latest('id');

        $logs = $query->paginate(25)->withQueryString();

        $metrics = [
            'total_events'  => ActivityLog::count(),
            'today_events'  => ActivityLog::whereDate('created_at', today())->count(),
            'user_events'   => ActivityLog::where('actor_type', '!=', 'system')->count(),
            'system_events' => ActivityLog::where(function ($q) {
                $q->where('actor_type', 'system')->orWhereNull('user_id');
            })->count(),
        ];

        $users = User::select(['id', 'name', 'role', 'email'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/ActivityLogs/Index', [
            'logs'    => $logs,
            'filters' => [
                'actor'       => $actor,
                'action_type' => $actionType,
                'start_date'  => $startDate,
                'end_date'    => $endDate,
                'search'      => $search,
            ],
            'metrics' => $metrics,
            'users'   => $users,
        ]);
    }

    /**
     * Stream CSV export of filtered activity logs.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorizeSuperAdmin();

        $actor = $request->input('actor');
        $actionType = $request->input('action_type');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $search = $request->input('search');

        $query = ActivityLog::with('user:id,name,email,role')
            ->forActor($actor)
            ->forActionType($actionType)
            ->dateRange($startDate, $endDate)
            ->search($search)
            ->latest('id');

        $filename = 'activity_logs_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                'Log ID',
                'Timestamp (WAT)',
                'Actor Type',
                'Actor Name',
                'Actor Email',
                'Action Category',
                'Description',
                'IP Address',
                'User Agent',
            ]);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at?->format('Y-m-d H:i:s'),
                        strtoupper($log->actor_type ?? ($log->user_id ? 'ADMIN' : 'SYSTEM')),
                        $log->actor_name ?? ($log->user?->name ?? 'System Bot'),
                        $log->user?->email ?? 'N/A (System)',
                        strtoupper($log->action_type ?? 'GENERAL'),
                        $log->description,
                        $log->ip_address ?? '127.0.0.1 (CLI)',
                        $log->user_agent ?? 'N/A',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}

