<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'purchase_number',
        'supplier_name',
        'invoice_reference',
        'user_id',
        'purchase_date',
        'total_cost',
        'total_items_count',
        'payment_status',
        'notes',
    ];

    protected $casts = [
        'purchase_date'     => 'date',
        'total_cost'        => 'float',
        'total_items_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
}

