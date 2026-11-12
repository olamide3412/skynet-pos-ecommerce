<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'product_variant_mapping_id',
        'user_id',
        'inventory_source',
        'action_type',
        'reference_type',
        'reference_id',
        'reference_number',
        'previous_stock',
        'quantity_change',
        'new_stock',
        'unit_cost',
        'reason',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'previous_stock'  => 'integer',
        'quantity_change' => 'integer',
        'new_stock'       => 'integer',
        'unit_cost'       => 'float',
        'created_at'      => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variantMapping(): BelongsTo
    {
        return $this->belongsTo(ProductVariantMapping::class, 'product_variant_mapping_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

