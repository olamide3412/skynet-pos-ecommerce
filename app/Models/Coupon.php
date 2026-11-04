<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order_amount',
        'max_uses',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
        'exclude_sale_items',
    ];

    protected $casts = [
        'value'              => 'decimal:2',
        'min_order_amount'   => 'decimal:2',
        'starts_at'          => 'datetime',
        'expires_at'         => 'datetime',
        'is_active'          => 'boolean',
        'exclude_sale_items' => 'boolean',
    ];

    public function isValidForAmount(float $subtotal): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && now()->gt($this->expires_at)) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        if ($this->min_order_amount > 0 && $subtotal < (float) $this->min_order_amount) {
            return false;
        }

        return true;
    }

    /**
     * Calculate discount amount, respecting exclude_sale_items policy.
     */
    public function calculateDiscount(float $subtotal, ?Cart $cart = null): float
    {
        if (!$this->isValidForAmount($subtotal)) {
            return 0.0;
        }

        $eligibleAmount = $subtotal;

        // If coupon excludes items on sale / flash sale, calculate against non-sale items only
        if ($this->exclude_sale_items && $cart && $cart->items && $cart->items->isNotEmpty()) {
            $eligibleAmount = 0.0;
            foreach ($cart->items as $item) {
                $product = $item->product;
                if ($product && !$product->is_on_sale) {
                    $eligibleAmount += ($item->price * $item->quantity);
                }
            }
        }

        if ($eligibleAmount <= 0) {
            return 0.0;
        }

        if ($this->type === 'percent' || $this->type === 'percentage') {
            $discount = ($eligibleAmount * (float) $this->value) / 100;
        } else {
            $discount = (float) $this->value;
        }

        return min($discount, $eligibleAmount);
    }
}

