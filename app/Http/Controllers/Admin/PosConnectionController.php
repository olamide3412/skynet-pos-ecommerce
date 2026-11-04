<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PosSyncLog;
use App\Services\PosIntegration\PosConnectionService;
use Inertia\Inertia;

class PosConnectionController extends Controller
{
    /**
     * Display the dedicated POS Branch Connection & Pairing management page.
     */
    public function index(PosConnectionService $connectionService)
    {
        return Inertia::render('Admin/PosConnection/Index', [
            'pos_connection' => $connectionService->getConnectionSummary(),
            'connection_logs' => PosSyncLog::whereIn('type', ['connection_test', 'webhook_ping', 'webhook_inventory', 'webhook_auth'])
                ->latest()
                ->paginate(25)
                ->withQueryString(),
        ]);
    }
}
