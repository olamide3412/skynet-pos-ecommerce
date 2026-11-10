<?php

namespace App\Traits;

use App\Models\Cart;
use App\Models\Customer;

trait MergesGuestCart
{
    /**
     * Merge guest session cart items into customer cart after login or registration.
     */
    protected function mergeGuestCart(?string $guestSessionId, ?Customer $customer): void
    {
        if (!$guestSessionId || !$customer) {
            return;
        }

        $guestCart = Cart::with('items')->where('session_id', $guestSessionId)->first();
        if (!$guestCart || $guestCart->items->isEmpty()) {
            if ($guestCart) {
                $guestCart->delete();
            }
            return;
        }

        $customerCart = Cart::with('items')->firstOrCreate(
            ['customer_id' => $customer->id],
            ['session_id' => null]
        );

        foreach ($guestCart->items as $guestItem) {
            $existing = $customerCart->items()
                ->where('product_id', $guestItem->product_id)
                ->where('product_pos_unit_id', $guestItem->product_pos_unit_id)
                ->where(function ($q) use ($guestItem) {
                    if ($guestItem->product_variant_mapping_id) {
                        $q->where('product_variant_mapping_id', $guestItem->product_variant_mapping_id)
                          ->orWhere('variant', $guestItem->variant);
                    } else {
                        $q->where('variant', $guestItem->variant);
                    }
                })
                ->first();

            if ($existing) {
                $existing->update([
                    'quantity'                   => max($existing->quantity, $guestItem->quantity),
                    'price'                      => $guestItem->price,
                    'product_variant_mapping_id' => $guestItem->product_variant_mapping_id ?: $existing->product_variant_mapping_id,
                ]);
            } else {
                $customerCart->items()->create([
                    'product_id'                 => $guestItem->product_id,
                    'product_pos_unit_id'        => $guestItem->product_pos_unit_id,
                    'product_variant_mapping_id' => $guestItem->product_variant_mapping_id,
                    'unit_name'                  => $guestItem->unit_name,
                    'quantity'                   => $guestItem->quantity,
                    'price'                      => $guestItem->price,
                    'variant'                    => $guestItem->variant,
                    'variant_key'                => $guestItem->variant_key,
                    'variant_label'              => $guestItem->variant_label,
                ]);
            }
        }

        // Clean up guest cart items and container
        $guestCart->items()->delete();
        $guestCart->delete();
    }

    /**
     * Snapshot guest session cart items into customer cart without deleting the guest cart.
     * Used when a user registers or attempts unverified login, guaranteeing cart preservation across devices.
     */
    protected function syncGuestCartToUnverifiedCustomer(?string $guestSessionId, ?Customer $customer): void
    {
        if (!$guestSessionId || !$customer) {
            return;
        }

        $guestCart = Cart::with('items')->where('session_id', $guestSessionId)->first();
        if (!$guestCart || $guestCart->items->isEmpty()) {
            return;
        }

        $customerCart = Cart::with('items')->firstOrCreate(
            ['customer_id' => $customer->id],
            ['session_id' => null]
        );

        foreach ($guestCart->items as $guestItem) {
            $existing = $customerCart->items()
                ->where('product_id', $guestItem->product_id)
                ->where('product_pos_unit_id', $guestItem->product_pos_unit_id)
                ->where(function ($q) use ($guestItem) {
                    if ($guestItem->product_variant_mapping_id) {
                        $q->where('product_variant_mapping_id', $guestItem->product_variant_mapping_id)
                          ->orWhere('variant', $guestItem->variant);
                    } else {
                        $q->where('variant', $guestItem->variant);
                    }
                })
                ->first();

            if ($existing) {
                $existing->update([
                    'quantity'                   => max($existing->quantity, $guestItem->quantity),
                    'price'                      => $guestItem->price,
                    'product_variant_mapping_id' => $guestItem->product_variant_mapping_id ?: $existing->product_variant_mapping_id,
                ]);
            } else {
                $customerCart->items()->create([
                    'product_id'                 => $guestItem->product_id,
                    'product_pos_unit_id'        => $guestItem->product_pos_unit_id,
                    'product_variant_mapping_id' => $guestItem->product_variant_mapping_id,
                    'unit_name'                  => $guestItem->unit_name,
                    'quantity'                   => $guestItem->quantity,
                    'price'                      => $guestItem->price,
                    'variant'                    => $guestItem->variant,
                    'variant_key'                => $guestItem->variant_key,
                    'variant_label'              => $guestItem->variant_label,
                ]);
            }
        }
    }
}

