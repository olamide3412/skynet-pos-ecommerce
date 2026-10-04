<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\PosIntegration\PosReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    protected PosReservationService $posReservationService;

    public function __construct(?PosReservationService $posReservationService = null)
    {
        $this->posReservationService = $posReservationService ?: new PosReservationService();
    }

    /**
     * Deduct inventory for ecommerce-only items in an order within a DB transaction.
     * POS-linked items are NOT deducted locally because physical inventory is owned by POS (Section 140L).
     */
    public function deductOrderInventory(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if ($item->inventory_source === 'ecommerce' && $item->product_id) {
                    $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                    if ($product) {
                        $newStock = max(0, $product->stock - $item->quantity);
                        $product->update(['stock' => $newStock]);
                    }
                }
            }
        });
    }

    /**
     * Restore stock for ecommerce-only items when an order is cancelled or returned (Section 140M).
     */
    public function restoreOrderInventory(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if ($item->inventory_source === 'ecommerce' && $item->product_id) {
                    $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
                    if ($product) {
                        $product->increment('stock', $item->quantity);
                    }
                }
            }

            // For POS-linked items, release active reservation if not yet confirmed
            if ($order->pos_reservation_status === 'reserved') {
                $this->posReservationService->releaseReservation($order);
            }
        });
    }

    /**
     * Check if a product has sufficient stock available.
     */
    public function hasStock(Product $product, int $quantity = 1): bool
    {
        return $product->stock >= $quantity && $product->isAvailableForPurchase();
    }
}
