<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PosConnection;
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
        $event = (string) $request->header('X-Skynet-Event', $request->input('event', ''));
        $rawBranchId = $request->header('X-Branch-Id', $request->input('branch_id'));
        $branchId = $rawBranchId !== null ? (int) $rawBranchId : null;

        Log::info("POS Webhook received: [{$event}] for branch [{$branchId}]", [
            'ip'        => $request->ip(),
            'event'     => $event,
            'branch_id' => $branchId,
        ]);

        // Guard 1 (Connection Check): Retrieve the POS connection for the requested branch ID
        $connection = ($branchId !== null)
            ? (PosConnection::where('pos_branch_id', $branchId)->first() ?: PosConnection::active())
            : PosConnection::active();

        if (!$connection) {
            Log::warning("POS Webhook rejected: No POS connection configured in Ecommerce store.");
            PosSyncLog::log('webhook_auth', 'failed', 'POS Webhook rejected: No active POS connection configured.', null, [
                'branch_id' => $branchId,
                'ip'        => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: No POS connection configured on Ecommerce store.',
            ], 403);
        }

        // Guard 2 (Branch Isolation): Ensure X-Branch-Id matches $connection->pos_branch_id. Reject with 403 if mismatch.
        if ($rawBranchId === null || (string) $rawBranchId !== (string) $connection->pos_branch_id) {
            Log::warning("POS Webhook rejected: Branch mismatch. Store connected to branch [{$connection->pos_branch_id}], webhook sent for [" . ($rawBranchId ?? 'none') . "].");
            PosSyncLog::log('webhook_auth', 'failed', "POS Webhook rejected: Branch mismatch. Connected: {$connection->pos_branch_id}, Received: " . ($rawBranchId ?? 'none') . ".", null, [
                'expected_branch_id' => $connection->pos_branch_id,
                'received_branch_id' => $rawBranchId,
                'ip'                 => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Unauthorized branch: This store is paired with POS Branch #{$connection->pos_branch_id}.",
            ], 403);
        }

        // Guard 3 (Authentication & Signature Verification): Decrypt the stored secret using $connection->getDecryptedApiSecret().
        $expectedSecret = $connection->getDecryptedApiSecret();

        if (empty($expectedSecret)) {
            Log::warning("POS Webhook rejected: POS connection has no API secret configured.");
            PosSyncLog::log('webhook_auth', 'failed', 'POS Webhook rejected: Stored connection lacks API secret credentials.', null, [
                'branch_id' => $branchId,
                'ip'        => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: POS connection is missing API secret credentials.',
            ], 401);
        }

        // a) Bearer token or X-Commerce-Key matches decrypted secret via hash_equals
        $providedToken = $request->bearerToken() ?: $request->header('X-Commerce-Key');
        $isTokenValid = !empty($providedToken) && hash_equals($expectedSecret, $providedToken);

        // b) HMAC signature matches sha256=' . hash_hmac('sha256', $request->getContent(), $secret)
        $providedSignature = $request->header('X-Skynet-Signature');
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $request->getContent(), $expectedSecret);
        $isSignatureValid = !empty($providedSignature) && hash_equals($expectedSignature, $providedSignature);

        if (!$isTokenValid && !$isSignatureValid) {
            Log::warning("POS Webhook rejected: Invalid credentials or signature from IP [{$request->ip()}] for branch [{$branchId}].");
            PosSyncLog::log('webhook_auth', 'failed', 'POS Webhook rejected: Authentication failed. Invalid API secret token or signature.', null, [
                'branch_id'     => $branchId,
                'has_token'     => !empty($providedToken),
                'has_signature' => !empty($providedSignature),
                'ip'            => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid POS webhook credentials or signature.',
            ], 401);
        }

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
            $buyingPrice = $data['buying_price'] ?? $data['cost_price'] ?? $data['purchase_price'] ?? null;

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

                // Update product cost/buying price if sent
                if ($buyingPrice !== null && is_numeric($buyingPrice)) {
                    $product->cost_price = (float) $buyingPrice;
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
                    if ($buyingPrice !== null && is_numeric($buyingPrice)) {
                        $syncedUpdates['buying_price'] = (float) $buyingPrice;
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
