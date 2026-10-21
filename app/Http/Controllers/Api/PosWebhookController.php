<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PosSyncLog;
use App\Models\Product;
use App\Models\SyncedPosItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PosWebhookController extends Controller
{
    /**
     * Handle incoming real-time webhooks from Skynet Digital POS.
     */
    public function handle(Request $request): JsonResponse
    {
        $event = $request->header('X-Skynet-Event', $request->input('event'));
        $branchId = $request->header('X-Branch-Id', $request->input('branch_id'));

        Log::info("POS Webhook received: [{$event}] for branch [{$branchId}]", $request->all());

        // 1. Handle Connection Verification / Test Ping
        if ($event === 'test.ping') {
            PosSyncLog::log('webhook_ping', 'success', 'Test webhook successfully received and acknowledged by Ecommerce server.', null, [
                'branch_id' => $branchId,
                'ip'        => $request->ip(),
            ]);

            return response()->json([
                'success'   => true,
                'message'   => 'Test webhook successfully received and acknowledged by Ecommerce server.',
                'timestamp' => now()->toIso8601String(),
            ], 200);
        }

        // 2. Handle Real-time Inventory & Price Updates
        if ($event === 'inventory.updated' || $event === 'item.price_updated') {
            $data = $request->input('data', []);
            $posItemId = $data['pos_item_id'] ?? null;
            $barcode = $data['barcode_number'] ?? null;
            $availableQty = $data['available_qty'] ?? null;
            $price = $data['price'] ?? null;

            if (!$posItemId && !$barcode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing pos_item_id or barcode identifier.',
                ], 422);
            }

            // Strategy A: Match by pos_item_id column on Product
            $product = null;
            if ($posItemId) {
                $product = Product::where('pos_item_id', $posItemId)->first();
            }

            // Strategy B: Match by SKU / Barcode if not found
            if (!$product && $barcode) {
                $product = Product::where('sku', $barcode)->first();
            }

            // Strategy C: Match via SyncedPosItem mapping
            if (!$product && $posItemId) {
                $syncedItem = SyncedPosItem::where('pos_item_id', $posItemId)->first();
                if ($syncedItem) {
                    $product = Product::where('pos_item_id', $syncedItem->pos_item_id)->first();
                }
            }

            if ($product) {
                // Update product stock
                if ($availableQty !== null && is_numeric($availableQty)) {
                    $product->stock = (int) floor($availableQty);
                }

                // Update product price if sent
                if ($price !== null && is_numeric($price)) {
                    $product->price = (float) $price;
                }

                $product->last_pos_sync_at = now();
                $product->save();

                // Also update local cached synced_pos_items table if present
                if ($posItemId) {
                    $syncedUpdates = ['last_synced_at' => now()];
                    if ($availableQty !== null && is_numeric($availableQty)) {
                        $syncedUpdates['effective_availability'] = (int) round((float) $availableQty);
                    }
                    if ($price !== null && is_numeric($price)) {
                        $syncedUpdates['standard_price'] = (float) $price;
                    }
                    SyncedPosItem::where('pos_item_id', $posItemId)->update($syncedUpdates);
                }

                $logMsg = "Webhook updated Product #{$product->id} ({$product->name}) stock to {$product->stock}";
                if ($price !== null && is_numeric($price)) {
                    $logMsg .= " and price to {$product->price}";
                }

                PosSyncLog::log('webhook_inventory', 'success', $logMsg, (string) $product->id, [
                    'pos_item_id'   => $posItemId,
                    'stock'         => $product->stock,
                    'price'         => $product->price,
                    'branch_id'     => $branchId,
                ]);

                return response()->json([
                    'success'    => true,
                    'message'    => "Product #{$product->id} updated successfully.",
                    'product_id' => $product->id,
                    'stock'      => $product->stock,
                    'price'      => $product->price,
                ], 200);
            }

            Log::warning("POS item #{$posItemId} (Barcode: {$barcode}) not found in ecommerce catalog.");

            PosSyncLog::log('webhook_inventory', 'warning', "Webhook received for unmapped POS item #{$posItemId} (Barcode: {$barcode}). No product updated.", (string) $posItemId);

            return response()->json([
                'success' => true,
                'message' => 'Webhook received, but matching product was not found in ecommerce store.',
            ], 200);
        }

        // Acknowledge any other events cleanly
        return response()->json([
            'success' => true,
            'message' => "Event '{$event}' acknowledged.",
        ], 200);
    }
}
