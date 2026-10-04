<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'guest_email', 'total_amount', 'status',
        'payment_method', 'payment_reference', 'tracking_number',
        'billing_name', 'billing_email', 'billing_phone', 'billing_address', 'delivery_method',
        // POS Reservation fields
        'inventory_reference', 'pos_reservation_status', 'pos_reservation_expires_at',
        'pos_reservation_confirmed_at', 'pos_reservation_released_at', 'pos_sync_error',
    ];

    protected $casts = [
        'total_amount'                 => 'decimal:2',
        'pos_reservation_expires_at'   => 'datetime',
        'pos_reservation_confirmed_at' => 'datetime',
        'pos_reservation_released_at'  => 'datetime',
    ];

    /**
     * Check if POS reservation is active.
     */
    public function isPosReserved(): bool
    {
        return $this->pos_reservation_status === 'reserved';
    }

    /**
     * Check if POS reservation has been confirmed.
     */
    public function isPosConfirmed(): bool
    {
        return $this->pos_reservation_status === 'confirmed';
    }

    /**
     * Auto-generate a unique alphanumeric tracking number on creation.
     */
    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->tracking_number)) {
                do {
                    // Format: SKY-XXXXXXXX  (alphanumeric, uppercase)
                    $tracking = 'SKY-' . strtoupper(Str::random(8));
                } while (self::where('tracking_number', $tracking)->exists());

                $order->tracking_number = $tracking;
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
