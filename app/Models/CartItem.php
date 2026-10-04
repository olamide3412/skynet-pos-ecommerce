<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'price',
        'variant',
        'product_pos_unit_id',
        'unit_name',
        'variant_key',
        'variant_label',
    ];

    protected $with = ['product', 'posUnit'];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function posUnit(): BelongsTo
    {
        return $this->belongsTo(ProductPosUnit::class, 'product_pos_unit_id');
    }
}
