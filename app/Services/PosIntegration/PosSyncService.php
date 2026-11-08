<?php

namespace App\Services\PosIntegration;

use App\Models\Cart;
use App\Models\PosConnection;
use App\Models\PosSyncLog;
use App\Models\Product;
use App\Models\ProductPosUnit;
use App\Models\SyncedPosItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PosSyncService
{
    protected PosApiClient $apiClient;

    public function __construct(?PosApiClient $apiClient = null)
    {
        $this->apiClient = $apiClient ?: new PosApiClient();
    }

    /**
     * Refresh availability for all currently mapped POS items in chunked batches.
     */
    public function syncAvailability(): array
    {
        @set_time_limit(300);

        $conn = PosConnection::active();
        if (!$conn || !$conn->is_connected || empty($conn->pos_base_url)) {
            return ['status' => false, 'message' => 'No active or connected POS branch.'];
        }

        $mappedPosIds = array_values(array_unique(
            Product::where('inventory_source', 'pos')
                ->whereNotNull('pos_item_id')
                ->pluck('pos_item_id')
                ->map(fn ($id) => (int) $id)
                ->toArray()
        ));

        if (empty($mappedPosIds)) {
            return ['status' => true, 'message' => 'No mapped POS items to synchronize.', 'updated_count' => 0];
        }

        $mappedLookup = array_flip($mappedPosIds);
        $updatedCount = 0;

        try {
            // Process in batches of 100 items per API request to avoid timeouts on 1,000+ items
            foreach (array_chunk($mappedPosIds, 100) as $chunkIds) {
                $response = $this->apiClient->getAvailability($chunkIds);
                $availabilityData = $response['data'] ?? [];
                $now = now();

                DB::transaction(function () use ($availabilityData, $mappedLookup, $now, &$updatedCount) {
                    foreach ($availabilityData as $key => $val) {
                        $posItemId = null;
                        $numericQty = 0.0;

                        if (is_array($val)) {
                            $posItemId = $val['pos_item_id'] ?? $val['item_id'] ?? $val['id'] ?? null;
                            if (!$posItemId && is_numeric($key) && isset($mappedLookup[(int) $key])) {
                                $posItemId = (int) $key;
                            }

                            if (isset($val['available_quantity']) && is_numeric($val['available_quantity'])) {
                                $numericQty = (float) $val['available_quantity'];
                            } elseif (isset($val['effective_availability']) && is_numeric($val['effective_availability'])) {
                                $numericQty = (float) $val['effective_availability'];
                            } elseif (isset($val['quantity']) && is_numeric($val['quantity'])) {
                                $numericQty = (float) $val['quantity'];
                            } elseif (isset($val['stock']) && is_numeric($val['stock'])) {
                                $numericQty = (float) $val['stock'];
                            } elseif (isset($val['stock']) && is_array($val['stock'])) {
                                $sum = 0;
                                foreach ($val['stock'] as $st) {
                                    if (is_array($st)) {
                                        $sum += (float) ($st['quantity'] ?? $st['available_quantity'] ?? 0);
                                    } elseif (is_numeric($st)) {
                                        $sum += (float) $st;
                                    }
                                }
                                $numericQty = (float) $sum;
                            }
                        } elseif (is_numeric($val)) {
                            $posItemId = is_numeric($key) ? (int) $key : null;
                            $numericQty = (float) $val;
                        }

                        if ($posItemId && isset($mappedLookup[(int) $posItemId])) {
                            SyncedPosItem::where('pos_item_id', $posItemId)->update([
                                'effective_availability' => (int) round($numericQty),
                                'last_synced_at'         => $now,
                            ]);

                            Product::where('pos_item_id', $posItemId)->update([
                                'stock'            => (int) floor($numericQty),
                                'last_pos_sync_at' => $now,
                            ]);

                            $updatedCount++;
                        }
                    }
                });
            }

            $conn->update([
                'is_connected'               => true,
                'last_inventory_sync_at'     => now(),
                'last_successful_request_at' => now(),
                'last_error_at'              => null,
                'last_error_message'         => null,
            ]);

            PosSyncLog::log('availability_sync', 'success', "Stock availability synchronized for {$updatedCount} items");

            return [
                'status'        => true,
                'updated_count' => $updatedCount,
                'message'       => "Stock availability synchronized for {$updatedCount} items.",
            ];
        } catch (\Throwable $e) {
            Log::error('POS availability sync error: ' . $e->getMessage());
            PosSyncLog::log('availability_sync', 'failed', 'Availability sync failed: ' . $e->getMessage());
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Revalidate cart items before checkout.
     * Updates item prices to current effective prices and verifies stock availability.
     * Returns array with: ['valid' => bool, 'price_changed' => bool, 'out_of_stock' => bool, 'messages' => array]
     */
    public function revalidateCart(Cart $cart): array
    {
        if ($cart->exists) {
            $cart->loadMissing(['items.product.posUnits', 'items.posUnit', 'items.variantMapping.syncedPosItem']);
        }

        $priceChanged = false;
        $outOfStock = false;
        $messages = [];

        foreach ($cart->items as $item) {
            $product = $item->product;

            if (!$product || !$product->status) {
                $outOfStock = true;
                $messages[] = "Product '{$product?->name}' is no longer available.";
                continue;
            }

            // Check if item is linked to a POS-controlled variant mapping
            $variantMapping = $item->variantMapping;
            if ($variantMapping && $variantMapping->isPosControlled()) {
                $syncedItem = $variantMapping->syncedPosItem;
                if (!$syncedItem || !$syncedItem->is_active) {
                    $outOfStock = true;
                    $messages[] = "Option '{$variantMapping->variant_label}' for '{$product->name}' is currently unavailable.";
                    continue;
                }

                $variantPrice = (float) $syncedItem->standard_price;
                if (abs($variantPrice - floatval($item->price)) > 0.01) {
                    $priceChanged = true;
                    $messages[] = "Price for '{$product->name} ({$variantMapping->variant_label})' has updated to ₦" . number_format($variantPrice, 2) . ".";
                    $item->update(['price' => $variantPrice]);
                }

                $availableStock = (int) floor($syncedItem->effective_availability);
                if ($availableStock < $item->quantity) {
                    $outOfStock = true;
                    $messages[] = "Only {$availableStock} unit(s) available for '{$product->name} ({$variantMapping->variant_label})'.";
                }
                continue;
            }

            // Check if POS item is missing or inactive
            if ($product->isPosLinked() && in_array($product->pos_sync_status, ['missing_pos_item', 'inactive_pos_item'], true)) {
                $outOfStock = true;
                $messages[] = "'{$product->name}' is currently unavailable from inventory.";
                continue;
            }

            // Check selling unit availability
            $selectedUnit = $item->posUnit ?: ($item->product_pos_unit_id ? ProductPosUnit::find($item->product_pos_unit_id) : null);
            if ($item->product_pos_unit_id && (!$selectedUnit || !$selectedUnit->is_enabled_online)) {
                $outOfStock = true;
                $messages[] = "The selected unit for '{$product->name}' is no longer available.";
                continue;
            }

            // Calculate current effective price
            $currentEffectivePrice = $product->getEffectivePriceForUnit($selectedUnit);

            if (abs($currentEffectivePrice - floatval($item->price)) > 0.01) {
                $priceChanged = true;
                $messages[] = "Price for '{$product->name}' has updated from " . number_format($item->price, 2) . " to " . number_format($currentEffectivePrice, 2) . ".";
                $item->update(['price' => $currentEffectivePrice]);
            }

            // Check stock quantity
            if ($product->stock < $item->quantity) {
                $outOfStock = true;
                $messages[] = "Only {$product->stock} units available for '{$product->name}' (you requested {$item->quantity}).";
            }
        }

        return [
            'valid'         => !$outOfStock && !$priceChanged,
            'price_changed' => $priceChanged,
            'out_of_stock'  => $outOfStock,
            'messages'      => $messages,
        ];
    }
}
