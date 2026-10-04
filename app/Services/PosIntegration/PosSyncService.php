<?php

namespace App\Services\PosIntegration;

use App\Models\Cart;
use App\Models\PosConnection;
use App\Models\PosSyncLog;
use App\Models\Product;
use App\Models\ProductPosUnit;
use App\Models\SyncedPosItem;
use Illuminate\Support\Facades\Log;

class PosSyncService
{
    protected PosApiClient $apiClient;

    public function __construct(?PosApiClient $apiClient = null)
    {
        $this->apiClient = $apiClient ?: new PosApiClient();
    }

    /**
     * Refresh availability for all currently mapped POS items.
     */
    public function syncAvailability(): array
    {
        $conn = PosConnection::active();
        if (!$conn || !$conn->is_connected) {
            return ['status' => false, 'message' => 'No active POS connection.'];
        }

        $mappedPosIds = Product::where('inventory_source', 'pos')
            ->whereNotNull('pos_item_id')
            ->pluck('pos_item_id')
            ->toArray();

        if (empty($mappedPosIds)) {
            return ['status' => true, 'message' => 'No mapped POS items to synchronize.', 'updated_count' => 0];
        }

        try {
            $response = $this->apiClient->getAvailability($mappedPosIds);
            $availabilityData = $response['data'] ?? [];
            $updatedCount = 0;

            foreach ($availabilityData as $posItemId => $stockQty) {
                // Update local cached synced_pos_items
                SyncedPosItem::where('pos_item_id', $posItemId)->update([
                    'effective_availability' => floatval($stockQty),
                    'last_synced_at'         => now(),
                ]);

                // Update product stock
                Product::where('pos_item_id', $posItemId)->update([
                    'stock'            => (int) floor($stockQty),
                    'last_pos_sync_at' => now(),
                ]);

                $updatedCount++;
            }

            $conn->update([
                'last_inventory_sync_at'     => now(),
                'last_successful_request_at' => now(),
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
            $cart->loadMissing(['items.product.posUnits', 'items.posUnit']);
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
