<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'variant_key',
        'variant_label',
        'option_values',
        'pos_item_id',
        'inventory_source',
        'local_stock',
        'local_price',
        'is_active',
        'sort_order',
        'last_pos_sync_at',
    ];

    protected $casts = [
        'option_values'    => 'array',
        'is_active'        => 'boolean',
        'local_stock'      => 'integer',
        'local_price'      => 'decimal:2',
        'last_pos_sync_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function syncedPosItem(): BelongsTo
    {
        return $this->belongsTo(SyncedPosItem::class, 'pos_item_id', 'pos_item_id');
    }

    /**
     * Check if this variant is inventory-controlled by POS.
     */
    public function isPosControlled(): bool
    {
        return $this->inventory_source === 'pos' && !empty($this->pos_item_id);
    }
}
