<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryLogController extends Controller
{
    /**
     * Display the immutable stock movement audit ledger.
     */
    public function index(Request $request): Response
    {
        $query = InventoryLog::with([
            'product:id,name,sku,image,inventory_source',
            'variantMapping:id,variant_label',
            'user:id,name',
        ])->orderByDesc('id');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
            });
        }

        if ($actionType = $request->input('action_type')) {
            $query->where('action_type', $actionType);
        }

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($startDate = $request->input('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate = $request->input('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $logs = $query->paginate(30)->withQueryString();

        // Metrics calculations
        $totalLogsCount = InventoryLog::count();
        $totalUnitsSold = abs((int) InventoryLog::where('action_type', 'sale')->sum('quantity_change'));
        $totalUnitsPurchased = (int) InventoryLog::where('action_type', 'purchase')->sum('quantity_change');
        $thisMonthNet = (int) InventoryLog::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('quantity_change');

        // Selected product if filtered
        $selectedProduct = null;
        if ($request->filled('product_id')) {
            $selectedProduct = Product::select('id', 'name', 'sku', 'product_number', 'stock', 'inventory_source', 'price')
                ->find($request->input('product_id'));
        }

        // Lightweight initial batch of 25 products for instant picker display
        $initialProducts = Product::select('id', 'name', 'sku', 'product_number', 'stock', 'inventory_source', 'price')
            ->orderBy('name')
            ->limit(25)
            ->get();

        // Admin users list for filter dropdown
        $filterUsers = User::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Admin/Inventory/Logs', [
            'logs'            => $logs,
            'filters'         => $request->only(['search', 'action_type', 'product_id', 'user_id', 'start_date', 'end_date']),
            'filterProducts'  => $initialProducts,
            'initialProducts' => $initialProducts,
            'selectedProduct' => $selectedProduct,
            'filterUsers'     => $filterUsers,
            'metrics'         => [
                'total_logs_count'     => $totalLogsCount,
                'total_units_sold'     => $totalUnitsSold,
                'total_units_purchased'=> $totalUnitsPurchased,
                'this_month_net'       => $thisMonthNet,
            ],
        ]);
    }

    /**
     * AJAX search products for the Ledger Product Filter picker.
     * Returns matching products (capped at 25 items) to prevent heavy loads when catalog has 2000+ items.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));

        $query = Product::select('id', 'name', 'sku', 'product_number', 'stock', 'inventory_source', 'price');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('product_number', 'like', "%{$q}%")
                    ->orWhere('id', $q);
            });
        }

        $products = $query->orderBy('name')
            ->limit(25)
            ->get();

        return response()->json([
            'products' => $products,
        ]);
    }

    /**
     * Export inventory logs to CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $query = InventoryLog::with([
            'product:id,name,sku',
            'variantMapping:id,variant_label',
            'user:id,name',
        ])->orderByDesc('id');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
            });
        }

        if ($actionType = $request->input('action_type')) {
            $query->where('action_type', $actionType);
        }

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        $filename = 'inventory_logs_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF"); // UTF-8 BOM

            fputcsv($handle, [
                'Log ID',
                'Date & Time',
                'Product Name',
                'SKU',
                'Variant',
                'Action Type',
                'Movement',
                'Previous Stock',
                'New Stock',
                'Unit Cost (NGN)',
                'Reference #',
                'Performed By',
                'Reason',
                'Notes',
            ]);

            $query->chunk(250, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    $qtyChangeStr = ($row->quantity_change > 0 ? "+{$row->quantity_change}" : (string) $row->quantity_change);

                    fputcsv($handle, [
                        $row->id,
                        $row->created_at?->format('Y-m-d H:i:s') ?? '',
                        $row->product?->name ?? 'Deleted Product',
                        $row->product?->sku ?? $row->product?->product_number ?? '',
                        $row->variantMapping?->variant_label ?? '',
                        ucfirst($row->action_type),
                        $qtyChangeStr,
                        $row->previous_stock,
                        $row->new_stock,
                        $row->unit_cost !== null ? number_format((float) $row->unit_cost, 2, '.', '') : '',
                        $row->reference_number ?? '',
                        $row->user?->name ?? 'System / Online Customer',
                        $row->reason,
                        $row->notes ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
