<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PosConnection;
use App\Models\PosSyncLog;
use App\Models\Product;
use App\Models\SyncedPosItem;
use App\Services\PosIntegration\PosCatalogService;
use App\Services\PosIntegration\PosConnectionService;
use App\Services\PosIntegration\PosSyncService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PosIntegrationController extends Controller
{
    protected PosConnectionService $connectionService;
    protected PosCatalogService $catalogService;
    protected PosSyncService $syncService;

    public function __construct(
        PosConnectionService $connectionService,
        PosCatalogService $catalogService,
        PosSyncService $syncService
    ) {
        $this->connectionService = $connectionService;
        $this->catalogService = $catalogService;
        $this->syncService = $syncService;
    }

    /**
     * Display POS Integration management center.
     */
    public function index(Request $request)
    {
        $connectionSummary = $this->connectionService->getConnectionSummary();

        $query = SyncedPosItem::with(['units', 'mappedProduct:id,name,pos_item_id,price,stock,status'])
            ->latest('last_synced_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('pos_item_name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('mapping_status')) {
            $query->where('mapping_status', $status);
        }

        $syncedItems = $query->paginate(20)->withQueryString();

        $logs = PosSyncLog::latest()->limit(25)->get();

        $stats = [
            'total_synced'    => SyncedPosItem::count(),
            'mapped_simple'   => SyncedPosItem::where('mapping_status', 'mapped_simple')->count(),
            'mapped_variant'  => SyncedPosItem::where('mapping_status', 'mapped_variant')->count(),
            'unmapped'        => SyncedPosItem::where('mapping_status', 'unmapped')->count(),
            'missing_pos'     => Product::where('inventory_source', 'pos')->where('pos_sync_status', 'missing_pos_item')->count(),
        ];

        return Inertia::render('Admin/PosIntegration/Index', [
            'connection'   => $connectionSummary,
            'items'        => $syncedItems,
            'logs'         => $logs,
            'stats'        => $stats,
            'filters'      => $request->only(['search', 'mapping_status']),
        ]);
    }

    /**
     * Ping POS API to test branch connection.
     */
    public function testConnection()
    {
        $result = $this->connectionService->testConnection();

        if ($result['status']) {
            return back()->with('success', 'POS branch connection test passed successfully!');
        }

        return back()->with('error', 'Connection test failed: ' . ($result['message'] ?? 'Unknown error'));
    }

    /**
     * Trigger manual catalog synchronization.
     */
    public function syncCatalog(Request $request)
    {
        $conn = PosConnection::active();
        if (!$conn || !$conn->is_connected) {
            return back()->with('error', 'Cannot synchronize catalog: store is not connected to a verified POS branch.');
        }

        $forceFull = $request->boolean('force_full', false);
        $result = $this->catalogService->syncCatalog($forceFull);

        if ($result['status']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', 'Catalog sync failed: ' . ($result['message'] ?? 'Unknown error'));
    }

    /**
     * Trigger manual stock availability synchronization.
     */
    public function syncAvailability()
    {
        $conn = PosConnection::active();
        if (!$conn || !$conn->is_connected) {
            return back()->with('error', 'Cannot synchronize stock: store is not connected to a verified POS branch.');
        }

        $result = $this->syncService->syncAvailability();

        if ($result['status']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', 'Stock sync failed: ' . ($result['message'] ?? 'Unknown error'));
    }

    /**
     * Reconcile all mapped products with the POS deletion-protection registry.
     */
    public function reconcileMappings()
    {
        $conn = PosConnection::active();
        if (!$conn || !$conn->is_connected) {
            return back()->with('error', 'Cannot reconcile mappings: store is not connected to a verified POS branch.');
        }

        $result = $this->catalogService->reconcileAllMappings();

        if ($result['success'] ?? ($result['status'] ?? false)) {
            $count = $result['reconciled'] ?? 0;
            return back()->with('success', "Successfully reconciled {$count} product mapping(s) with POS deletion protection.");
        }

        return back()->with('error', 'Mapping reconciliation failed: ' . ($result['message'] ?? 'Unknown error'));
    }

    /**
     * Import a single POS item as draft simple product.
     */
    public function importSimple(Request $request)
    {
        $conn = PosConnection::active();
        if (!$conn || !$conn->is_connected) {
            return back()->with('error', 'Cannot import item: store is not connected to a verified POS branch.');
        }

        $request->validate([
            'pos_item_id' => 'required|exists:synced_pos_items,pos_item_id',
        ]);

        try {
            $product = $this->catalogService->importAsSimpleProduct($request->pos_item_id);
            return back()->with('success', "Imported '{$product->name}' as draft product.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Bulk import multiple POS items as draft simple products.
     */
    public function bulkImport(Request $request)
    {
        $conn = PosConnection::active();
        if (!$conn || !$conn->is_connected) {
            return back()->with('error', 'Cannot import items: store is not connected to a verified POS branch.');
        }

        $request->validate([
            'pos_item_ids'   => 'required|array',
            'pos_item_ids.*' => 'exists:synced_pos_items,pos_item_id',
        ]);

        $result = $this->catalogService->bulkImportSimpleProducts($request->pos_item_ids);

        return back()->with('success', "Imported {$result['total_imported']} products successfully.");
    }

    /**
     * Import all unmapped eligible POS items.
     */
    public function importAllEligible()
    {
        $conn = PosConnection::active();
        if (!$conn || !$conn->is_connected) {
            return back()->with('error', 'Cannot import items: store is not connected to a verified POS branch.');
        }

        $result = $this->catalogService->importAllEligible();

        return back()->with('success', "Imported {$result['total_imported']} eligible products.");
    }

    /**
     * Save pairing credentials and immediately test connection to POS branch.
     * Per AGENT_README_ECOMMERCE_CONNECTION_FIX.md Section 2.2.
     */
    public function saveAndTestConnection(Request $request)
    {
        $validated = $request->validate([
            'pos_base_url' => 'required|url',
            'client_id'    => 'required|string|max:191',
            'api_secret'   => 'required|string',
        ]);

        $result = $this->connectionService->saveAndTestConnection(
            $validated['pos_base_url'],
            $validated['client_id'],
            $validated['api_secret']
        );

        if ($request->wantsJson()) {
            return response()->json($result, $result['status'] ? 200 : 422);
        }

        if ($result['status']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    /**
     * API/Protected Handshake endpoint for POS pairing.
     */
    public function establishConnection(Request $request)
    {
        $params = $request->validate([
            'pos_base_url'       => 'required|url',
            'client_id'          => 'required|string',
            'api_secret'         => 'required|string',
            'pos_branch_id'      => 'nullable|integer',
            'pos_branch_name'    => 'nullable|string',
            'pos_integration_id' => 'nullable|string',
        ]);

        $result = $this->connectionService->saveAndTestConnection(
            $params['pos_base_url'],
            $params['client_id'],
            $params['api_secret']
        );

        if ($result['status']) {
            try {
                $this->catalogService->syncCatalog(true);
            } catch (\Throwable $e) {
                // Background sync can continue
            }
            if ($request->wantsJson()) {
                return response()->json($result);
            }
            return back()->with('success', $result['message']);
        }

        if ($request->wantsJson()) {
            return response()->json($result, 422);
        }

        return back()->with('error', $result['message']);
    }
}
