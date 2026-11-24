<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PosSyncLog;
use App\Services\PosIntegration\PosConnectionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PosConnectionController extends Controller
{
    public const CONNECTION_LOG_TYPES = [
        'connection_test',
        'webhook_ping',
        'webhook_inventory',
        'webhook_auth',
    ];

    /**
     * Display the dedicated POS Branch Connection & Pairing management page.
     */
    public function index(PosConnectionService $connectionService)
    {
        return Inertia::render('Admin/PosConnection/Index', [
            'pos_connection' => $connectionService->getConnectionSummary(),
            'connection_logs' => PosSyncLog::whereIn('type', self::CONNECTION_LOG_TYPES)
                ->latest()
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    /**
     * Clear connection & webhook audit logs older than 3, 7, or 30 days.
     */
    public function clearLogs(Request $request)
    {
        $validated = $request->validate([
            'days' => 'required|integer|in:3,7,30',
        ]);

        $days = (int) $validated['days'];
        $cutoff = now()->subDays($days);

        $deleted = PosSyncLog::whereIn('type', self::CONNECTION_LOG_TYPES)
            ->where('created_at', '<', $cutoff)
            ->delete();

        if ($deleted === 0) {
            return redirect()->route('admin.pos-connection.index')
                ->with('success', "No connection or webhook audit logs older than {$days} days were found.");
        }

        return redirect()->route('admin.pos-connection.index')
            ->with('success', "Cleared {$deleted} connection/webhook audit log(s) older than {$days} days.");
    }

    /**
     * Export connection & webhook audit logs as CSV.
     */
    public function exportLogs(): StreamedResponse
    {
        $filename = 'pos-connection-audit-logs-' . now()->format('Y-m-d_H-i') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['ID', 'Timestamp', 'Type', 'Status', 'Reference', 'Message', 'Context']);

            PosSyncLog::whereIn('type', self::CONNECTION_LOG_TYPES)
                ->latest()
                ->chunk(500, function ($logs) use ($handle) {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            $log->id,
                            optional($log->created_at)->format('Y-m-d H:i:s'),
                            $log->type,
                            $log->status,
                            $log->reference ?? '',
                            $log->message ?? '',
                            $log->context ? json_encode($log->context, JSON_UNESCAPED_UNICODE) : '',
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
