<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SyncedPosItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pos_item_id',
        'pos_item_name',
        'pos_category_id',
        'pos_category_name',
        'pos_brand_id',
        'pos_brand_name',
        'barcode',
        'item_reference',
        'sku',
        'is_active',
        'is_active_in_pos',
        'base_unit_name',
        'standard_price',
        'buying_price',
        'effective_availability',
        'imei_tracking_enabled',
        'mapping_status',
        'pos_updated_at',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active'              => 'boolean',
        'imei_tracking_enabled' => 'boolean',
        'standard_price'         => 'decimal:2',
        'buying_price'           => 'decimal:2',
        'effective_availability' => 'integer',
        'pos_updated_at'         => 'datetime',
        'last_synced_at'         => 'datetime',
    ];

    public function getIsActiveInPosAttribute(): bool
    {
        return (bool) ($this->attributes['is_active'] ?? false);
    }

    public function setIsActiveInPosAttribute($value): void
    {
        $this->attributes['is_active'] = (bool) $value;
    }

    public function units(): HasMany
    {
        return $this->hasMany(SyncedPosItemUnit::class, 'synced_pos_item_id');
    }

    public function mappedProduct(): HasOne
    {
        return $this->hasOne(Product::class, 'pos_item_id', 'pos_item_id');
    }

    public function variantMappings(): HasMany
    {
        return $this->hasMany(ProductVariantMapping::class, 'pos_item_id', 'pos_item_id');
    }
}
