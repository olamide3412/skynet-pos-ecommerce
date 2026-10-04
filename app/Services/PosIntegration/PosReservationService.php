<?php

namespace App\Services\PosIntegration;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosSyncLog;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PosReservationService
{
    protected PosApiClient $apiClient;

    public function __construct(?PosApiClient $apiClient = null)
    {
        $this->apiClient = $apiClient ?: new PosApiClient();
    }

    /**
     * Reserve stock for POS-linked lines in a cart before payment redirect.
     * Returns array with: ['status' => bool, 'inventory_reference' => string, 'message' => string, 'items_reserved' => int]
     */
    public function reserveForCart(Cart $cart): array
    {
        if ($cart->exists) {
            $cart->loadMissing(['items.product.posUnits', 'items.posUnit']);
        }
        $posLines = [];

        foreach ($cart->items as $cartItem) {
            $product = $cartItem->product;
            if (!$product) {
                continue;
            }

            // Determine if line is POS-linked
            if ($product->inventory_source === 'pos' && $product->pos_item_id) {
                $posLines[] = [
                    'cart_item_id'     => $cartItem->id,
                    'pos_item_id'      => $product->pos_item_id,
                    'pos_item_unit_id' => $cartItem->posUnit?->pos_item_unit_id ?? null,
                    'quantity'         => $cartItem->quantity,
                    'unit_name'        => $cartItem->posUnit?->unit_name ?? $product->pos_base_unit_name ?? 'Piece',
                ];
            }
        }

        // If no POS-linked items exist (all ecommerce only), no POS reservation is needed
        if (empty($posLines)) {
            return [
                'status'              => true,
                'inventory_reference' => (string) Str::uuid(),
                'has_pos_items'       => false,
                'items_reserved'      => 0,
            ];
        }

        // Must have configured POS API connection
        if (!$this->apiClient->isConfigured()) {
            throw new PosIntegrationException(
                "POS API is not connected. Unable to reserve physical inventory.",
                "Unable to checkout at this time because store inventory synchronization is temporarily offline."
            );
        }

        $reference = (string) Str::uuid();

        // Format payload strictly with POS-linked items (Section 140K)
        $reservationItems = array_map(function ($line) {
            return [
                'pos_item_id'      => $line['pos_item_id'],
                'pos_item_unit_id' => $line['pos_item_unit_id'],
                'quantity'         => $line['quantity'],
            ];
        }, $posLines);

        $result = $this->apiClient->createReservation($reference, $reservationItems, 30);

        if (!$result['status']) {
            $errorMsg = $result['message'] ?? 'One or more items in your cart are no longer available in the required quantity.';
            return [
                'status'  => false,
                'message' => $errorMsg,
                'errors'  => $result['errors'] ?? [],
            ];
        }

        return [
            'status'              => true,
            'inventory_reference' => $reference,
            'has_pos_items'       => true,
            'items_reserved'      => count($posLines),
            'reservation_data'    => $result['data'] ?? [],
        ];
    }

    /**
     * Confirm reservation when payment succeeds or COD is formally accepted.
     */
    public function confirmReservation(Order $order): bool
    {
        if (empty($order->inventory_reference) || $order->pos_reservation_status === 'confirmed') {
            return true;
        }

        // If order has no POS-linked items
        $hasPosLines = $order->items()->where('inventory_source', 'pos')->exists();
        if (!$hasPosLines) {
            $order->update(['pos_reservation_status' => 'confirmed']);
            return true;
        }

        try {
            $result = $this->apiClient->confirmReservation($order->inventory_reference);

            if ($result['status']) {
                $order->update([
                    'pos_reservation_status'       => 'confirmed',
                    'pos_reservation_confirmed_at' => now(),
                    'pos_sync_error'               => null,
                ]);
                return true;
            }

            // Record sync failure for queue retry without charging customer again (Section 52 & 53)
            $order->update([
                'pos_sync_error' => $result['message'] ?? 'POS confirmation pending retry',
            ]);

            Log::warning("POS reservation confirmation failed for Order #{$order->id} (Ref: {$order->inventory_reference}). Will queue for retry.");
            return false;
        } catch (\Throwable $e) {
            $order->update(['pos_sync_error' => $e->getMessage()]);
            Log::error("POS reservation confirmation exception for Order #{$order->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Release reservation when payment fails, is abandoned, or order is cancelled.
     */
    public function releaseReservation(Order $order): bool
    {
        if (empty($order->inventory_reference) || in_array($order->pos_reservation_status, ['none', 'released', 'confirmed'], true)) {
            return true;
        }

        try {
            $result = $this->apiClient->releaseReservation($order->inventory_reference);

            $order->update([
                'pos_reservation_status'      => 'released',
                'pos_reservation_released_at' => now(),
            ]);

            return $result['status'] ?? true;
        } catch (\Throwable $e) {
            Log::warning("Failed to release reservation {$order->inventory_reference}: " . $e->getMessage());
            return false;
        }
    }
}
