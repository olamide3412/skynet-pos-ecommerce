<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'cost_price',
        'variant',
        // POS Item, Unit & Variant Snapshots
        'inventory_source',
        'pos_item_id',
        'pos_item_unit_id',
        'unit_name',
        'quantity_in_base_units',
        'standard_pos_price_snapshot',
        'online_unit_price',
        'variant_key',
        'variant_label',
    ];

    protected $casts = [
        'price'                       => 'decimal:2',
        'cost_price'                  => 'decimal:2',
        'quantity_in_base_units'      => 'decimal:4',
        'standard_pos_price_snapshot' => 'decimal:2',
        'online_unit_price'           => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
