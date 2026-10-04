<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompareItem extends Model
{
    protected $fillable = ['compare_id', 'product_id'];

    public function compare(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Compare::class);
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
