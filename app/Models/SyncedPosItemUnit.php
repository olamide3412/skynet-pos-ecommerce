<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncedPosItemUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'synced_pos_item_id',
        'pos_item_unit_id',
        'unit_kind',
        'unit_name',
        'quantity_in_base_units',
        'standard_price',
    ];

    protected $casts = [
        'quantity_in_base_units' => 'decimal:4',
        'standard_price'         => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(SyncedPosItem::class, 'synced_pos_item_id');
    }
}
