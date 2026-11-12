<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPosUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'pos_item_unit_id',
        'unit_kind',
        'unit_name',
        'quantity_in_base_units',
        'synced_standard_price',
        'online_label',
        'image',
        'is_enabled_online',
        'sort_order',
        'last_pos_sync_at',
    ];

    protected $casts = [
        'is_enabled_online'      => 'boolean',
        'quantity_in_base_units' => 'decimal:4',
        'synced_standard_price'  => 'decimal:2',
        'last_pos_sync_at'       => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get display label for storefront.
     */
    public function getDisplayLabelAttribute(): string
    {
        return $this->online_label ?: $this->unit_name;
    }
}
