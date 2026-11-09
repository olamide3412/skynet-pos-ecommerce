<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PosConnection;
use App\Models\PosSyncLog;
use App\Models\Product;
use App\Models\ProductVariantMapping;
use App\Models\StoreSetting;
use App\Models\SyncedPosItem;
use App\Services\ActivityLogger;
use App\Services\Brands\PosBrandResolver;
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

        // 2. Handle Brand Lifecycle Webhooks (brand.created, brand.updated, brand.deleted)
        if ($event === 'brand.created' || $event === 'brand.updated') {
            $data = $request->input('data') ?: $request->input('brand') ?: $request->all();
            $resolver = new PosBrandResolver();
            $brand = $resolver->upsertFromPos(is_array($data) ? $data : []);

            if ($brand) {
                ActivityLogger::webhook(
                    "POS webhook [{$event}]: Synchronized brand '{$brand->name}' (#{$brand->id})",
                    ['pos_brand_id' => $brand->pos_brand_id, 'brand_id' => $brand->id, 'branch_id' => $branchId],
                    'POS Webhook'
                );
                PosSyncLog::log('webhook_brand', 'success', "Synchronized brand '{$brand->name}' via {$event}.", (string) $brand->id);
            }

            return response()->json([
                'success'  => true,
                'event'    => $event,
                'message'  => "Brand webhook '{$event}' processed.",
                'brand_id' => $brand?->id,
            ], 200);
        }

        if ($event === 'brand.deleted') {
            $data = $request->input('data') ?: $request->input('brand') ?: $request->all();
            $posBrandId = $data['pos_brand_id'] ?? $data['id'] ?? $data['brand_id'] ?? $request->input('pos_brand_id') ?? null;

            if ($posBrandId) {
                $resolver = new PosBrandResolver();
                $unlinked = $resolver->unlinkPosBrand($posBrandId);

                ActivityLogger::webhook(
                    "POS webhook [brand.deleted]: Unlinked POS brand #{$posBrandId} ({$unlinked} eCommerce brand preserved)",
                    ['pos_brand_id' => $posBrandId, 'branch_id' => $branchId],
                    'POS Webhook'
                );
                PosSyncLog::log('webhook_brand', 'success', "Unlinked deleted POS brand #{$posBrandId}.", (string) $posBrandId);
            }

            return response()->json([
                'success' => true,
                'event'   => $event,
                'message' => 'POS brand unlinked; eCommerce brand preserved.',
            ], 200);
        }

        // 3. Handle Real-time Inventory, Price & Item Metadata Updates
        if (in_array($event, ['inventory.updated', 'item.price_updated', 'item.updated'], true)) {
            $data = $request->input('data');
            if (empty($data) || !is_array($data)) {
                $itemsInput = $request->input('items');
                if (is_array($itemsInput) && !empty($itemsInput[0]) && is_array($itemsInput[0])) {
                    $data = $itemsInput[0];
                } elseif (is_array($request->input('item'))) {
                    $data = $request->input('item');
                } else {
                    $data = $request->all();
                }
            }
            $posItemId = $data['pos_item_id'] ?? $data['item_id'] ?? $data['id'] ?? null;
            $barcode = $data['barcode_number'] ?? $data['barcode'] ?? null;
            $availableQty = $data['available_qty'] ?? null;
            $price = $data['price'] ?? null;
            $buyingPrice = $data['buying_price'] ?? $data['buy_price'] ?? $data['cost_price'] ?? $data['purchase_price'] ?? null;
            $hasBrandField = array_key_exists('pos_brand_id', $data)
                || array_key_exists('brand_id', $data)
                || array_key_exists('pos_brand_name', $data)
                || array_key_exists('brand_name', $data)
                || isset($data['brand']);
            $posBrandId = $data['pos_brand_id'] ?? $data['brand_id'] ?? ($data['brand']['id'] ?? null);
            $posBrandName = $data['pos_brand_name'] ?? $data['brand_name'] ?? ($data['brand']['name'] ?? null);

            if (!$posItemId && !$barcode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing pos_item_id or barcode identifier.',
                ], 422);
            }

            // Always update local cached synced_pos_items table if present
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
                if (!empty($data['item_name']) || !empty($data['name'])) {
                    $syncedUpdates['pos_item_name'] = $data['item_name'] ?? $data['name'];
                }
                if (array_key_exists('pos_category_id', $data) || array_key_exists('category_id', $data)) {
                    $syncedUpdates['pos_category_id'] = $data['pos_category_id'] ?? $data['category_id'];
                }
                if (array_key_exists('pos_category_name', $data) || array_key_exists('category_name', $data)) {
                    $syncedUpdates['pos_category_name'] = $data['pos_category_name'] ?? $data['category_name'];
                }
                if ($hasBrandField) {
                    $syncedUpdates['pos_brand_id'] = $posBrandId;
                    $syncedUpdates['pos_brand_name'] = $posBrandName;
                }
                SyncedPosItem::where('pos_item_id', $posItemId)->update($syncedUpdates);
            }

            $resolvedWebhookBrandId = null;
            if ($hasBrandField && (!empty($posBrandId) || !empty($posBrandName))) {
                $resolver = new PosBrandResolver();
                $resolvedWebhookBrandId = $resolver->resolve($posBrandId, $posBrandName);
            }
            $overwriteBrand = StoreSetting::getBool('pos_brand_sync_overwrite', false);

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
                // Update product stock (if product has variants, Strategy D below will reconcile total variant stock)
                if ($availableQty !== null && is_numeric($availableQty) && empty($product->variants)) {
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

                // Apply brand overwrite policy when POS sends brand metadata
                if ($resolvedWebhookBrandId !== null && (empty($product->brand_id) || $overwriteBrand)) {
                    $product->brand_id = $resolvedWebhookBrandId;
                }

                $product->last_pos_sync_at = now();
                $product->save();

                if ($event === 'item.updated') {
                    ActivityLogger::webhook(
                        "POS webhook [item.updated]: Updated Product #{$product->id} ({$product->name})",
                        ['pos_item_id' => $posItemId, 'product_id' => $product->id, 'brand_id' => $product->brand_id],
                        'POS Webhook'
                    );
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
            }

            // Strategy D: Match via ProductVariantMapping (Multi-Variant POS integration)
            // Runs even if a standalone/parent Product also matched above!
            $variantMappings = collect();
            if ($posItemId) {
                $variantMappings = ProductVariantMapping::with('product')->where('pos_item_id', $posItemId)->get();
            }

            $firstUpdatedVariant = null;
            foreach ($variantMappings as $variantMapping) {
                $parentProduct = $variantMapping->product;

                if ($availableQty !== null && is_numeric($availableQty)) {
                    $variantMapping->local_stock = (int) floor($availableQty);
                }
                if ($price !== null && is_numeric($price)) {
                    $variantMapping->local_price = (float) $price;
                }
                $variantMapping->last_pos_sync_at = now();
                $variantMapping->save();

                if (!$firstUpdatedVariant) {
                    $firstUpdatedVariant = $variantMapping;
                }

                // Update parent product variants JSON so storefront stays synchronized
                if ($parentProduct && is_array($parentProduct->variants)) {
                    $updatedVariants = [];
                    foreach ($parentProduct->variants as $var) {
                        if (($var['pos_item_id'] ?? null) == $posItemId || ($var['name'] ?? null) === $variantMapping->variant_label) {
                            if ($availableQty !== null && is_numeric($availableQty)) {
                                $var['stock'] = (int) floor($availableQty);
                            }
                            if ($price !== null && is_numeric($price)) {
                                $var['price'] = (float) $price;
                            }
                        }
                        $updatedVariants[] = $var;
                    }
                    $totalVariantStock = !empty($updatedVariants)
                        ? (int) array_sum(array_column($updatedVariants, 'stock'))
                        : (int) ProductVariantMapping::where('product_id', $parentProduct->id)->sum('local_stock');

                    $parentUpdates = [
                        'stock'    => $totalVariantStock,
                        'variants' => $updatedVariants,
                    ];
                    if ($resolvedWebhookBrandId !== null && (empty($parentProduct->brand_id) || $overwriteBrand)) {
                        $parentUpdates['brand_id'] = $resolvedWebhookBrandId;
                    }

                    $parentProduct->updateQuietly($parentUpdates);
                }

                $logMsg = "Webhook updated Variant '{$variantMapping->variant_label}' (Product #{$parentProduct?->id}) stock to {$variantMapping->local_stock}";
                if ($price !== null && is_numeric($price)) {
                    $logMsg .= " and price to {$variantMapping->local_price}";
                }

                PosSyncLog::log('webhook_inventory', 'success', $logMsg, (string) ($parentProduct?->id ?? $posItemId), [
                    'pos_item_id' => $posItemId,
                    'stock'       => $variantMapping->local_stock,
                    'price'       => $variantMapping->local_price,
                    'branch_id'   => $branchId,
                    'type'        => 'variant',
                ]);
            }

            if ($product && !$firstUpdatedVariant) {
                return response()->json([
                    'success'    => true,
                    'message'    => "Product #{$product->id} updated successfully.",
                    'product_id' => $product->id,
                    'stock'      => $product->stock,
                    'price'      => $product->price,
                ], 200);
            }

            if ($firstUpdatedVariant) {
                return response()->json([
                    'success'            => true,
                    'message'            => "Variant '{$firstUpdatedVariant->variant_label}' updated successfully.",
                    'variant_mapping_id' => $firstUpdatedVariant->id,
                    'product_id'         => $firstUpdatedVariant->product_id,
                    'stock'              => $firstUpdatedVariant->local_stock,
                    'price'              => $firstUpdatedVariant->local_price,
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
