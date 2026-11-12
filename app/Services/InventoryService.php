<?php

namespace App\Services;

use App\Models\InventoryLog;
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
                        $prevStock = (int) $product->stock;
                        $newStock = max(0, $prevStock - (int) $item->quantity);
                        $product->update(['stock' => $newStock]);

                        $this->recordLog(
                            product: $product,
                            actionType: 'sale',
                            quantityChange: -(int) $item->quantity,
                            previousStock: $prevStock,
                            newStock: $newStock,
                            reason: 'Online Customer Checkout',
                            notes: "Purchased in Order #{$order->tracking_number}",
                            referenceType: Order::class,
                            referenceId: $order->id,
                            referenceNumber: $order->tracking_number,
                            variantMappingId: $item->product_variant_mapping_id
                        );
                    }

                    if ($item->product_variant_mapping_id) {
                        $mapping = \App\Models\ProductVariantMapping::where('id', $item->product_variant_mapping_id)->lockForUpdate()->first();
                        if ($mapping && $mapping->inventory_source === 'ecommerce') {
                            $mapping->update(['local_stock' => max(0, $mapping->local_stock - $item->quantity)]);
                        }
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
                        $prevStock = (int) $product->stock;
                        $newStock = $prevStock + (int) $item->quantity;
                        $product->update(['stock' => $newStock]);

                        $this->recordLog(
                            product: $product,
                            actionType: 'cancellation',
                            quantityChange: +(int) $item->quantity,
                            previousStock: $prevStock,
                            newStock: $newStock,
                            reason: 'Order Cancelled / Returned',
                            notes: "Restocked from Order #{$order->tracking_number}",
                            referenceType: Order::class,
                            referenceId: $order->id,
                            referenceNumber: $order->tracking_number,
                            variantMappingId: $item->product_variant_mapping_id
                        );
                    }

                    if ($item->product_variant_mapping_id) {
                        $mapping = \App\Models\ProductVariantMapping::where('id', $item->product_variant_mapping_id)->lockForUpdate()->first();
                        if ($mapping && $mapping->inventory_source === 'ecommerce') {
                            $mapping->increment('local_stock', $item->quantity);
                        }
                    }
                }
            }

            // For POS-linked items, release POS reservation (restores physical stock on POS if confirmed, or lifts hold if reserved)
            if (in_array($order->pos_reservation_status, ['reserved', 'confirmed'], true)) {
                $this->posReservationService->releaseReservation($order);
            }
        });
    }

    /**
     * Record an immutable inventory movement log entry.
     */
    public function recordLog(
        Product $product,
        string $actionType,
        int $quantityChange,
        int $previousStock,
        int $newStock,
        string $reason,
        ?string $notes = null,
        ?int $userId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceNumber = null,
        ?float $unitCost = null,
        ?int $variantMappingId = null
    ): InventoryLog {
        return InventoryLog::create([
            'product_id'                 => $product->id,
            'product_variant_mapping_id' => $variantMappingId,
            'user_id'                    => $userId,
            'inventory_source'           => $product->inventory_source ?? 'ecommerce',
            'action_type'                => $actionType,
            'reference_type'             => $referenceType,
            'reference_id'               => $referenceId,
            'reference_number'           => $referenceNumber,
            'previous_stock'             => $previousStock,
            'quantity_change'            => $quantityChange,
            'new_stock'                  => $newStock,
            'unit_cost'                  => $unitCost ?? $product->cost_price,
            'reason'                     => $reason,
            'notes'                      => $notes,
            'created_at'                 => now(),
        ]);
    }

    /**
     * Check if a product has sufficient stock available.
     */
    public function hasStock(Product $product, int $quantity = 1): bool
    {
        return $product->stock >= $quantity && $product->isAvailableForPurchase();
    }
}
