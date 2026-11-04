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
        'customer_id', 'created_by_staff_id', 'assigned_staff_id', 'remitted_by_staff_id', 'reconciled_by_admin_id',
        'guest_email', 'subtotal', 'total_amount', 'coupon_code', 'discount_amount', 'shipping_fee', 'tax_amount', 'service_fee', 'status',
        'payment_method', 'payment_reference', 'tracking_number',
        'billing_name', 'billing_email', 'billing_phone', 'billing_address', 'delivery_method',
        // POS Reservation fields
        'inventory_reference', 'pos_reservation_status', 'pos_reservation_expires_at',
        'pos_reservation_confirmed_at', 'pos_reservation_released_at', 'pos_sync_error',
        // POD fields
        'pod_status', 'pod_amount_collected', 'pod_collected_at', 'pod_remitted_at',
        'pod_reconciled_at', 'pod_reconciliation_note',
    ];

    protected $casts = [
        'subtotal'                     => 'decimal:2',
        'total_amount'                 => 'decimal:2',
        'discount_amount'              => 'decimal:2',
        'shipping_fee'                 => 'decimal:2',
        'tax_amount'                   => 'decimal:2',
        'service_fee'                  => 'decimal:2',
        'pod_amount_collected'         => 'decimal:2',
        'pos_reservation_expires_at'   => 'datetime',
        'pos_reservation_confirmed_at' => 'datetime',
        'pos_reservation_released_at'  => 'datetime',
        'pod_collected_at'             => 'datetime',
        'pod_remitted_at'              => 'datetime',
        'pod_reconciled_at'            => 'datetime',
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

    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_staff_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function remittedByStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remitted_by_staff_id');
    }

    public function reconciledByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_admin_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Alias for items relationship.
     */
    public function orderItems(): HasMany
    {
        return $this->items();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at', 'desc')->orderBy('id', 'desc');
    }

    /**
     * Record a lifecycle event or status transition on this order.
     */
    public function recordStatusHistory(
        string $toStatus,
        ?string $fromStatus = null,
        string $event = 'status_change',
        ?string $comment = null,
        ?int $userId = null,
        ?array $metadata = null
    ): OrderStatusHistory {
        // Customer storefront order placement events are logged under System without staff/admin attribution
        $actorId = ($event === 'order_placed') ? null : ($userId ?? auth('web')->id());

        return $this->statusHistories()->create([
            'user_id'     => $actorId,
            'event'       => $event,
            'from_status' => $fromStatus ?? $this->status,
            'to_status'   => $toStatus,
            'comment'     => $comment,
            'metadata'    => $metadata,
        ]);
    }
}
