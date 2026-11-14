<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'brand_id', 'name', 'slug', 'url_key', 'product_number',
        'sku', 'description',
        'price', 'cost_price', 'discount_price',
        'special_price', 'special_price_from', 'special_price_to',
        'stock', 'expiry_date', 'image', 'images', 'variants', 'attributes', 'related_products', 'status',
        'is_new', 'is_featured', 'visible_individually', 'show_stock_level',
        'short_description', 'meta_title', 'meta_description', 'meta_keywords',
        'warranty', 'return_policy',
        // POS Integration fields
        'inventory_source', 'pos_item_id', 'pos_item_name_snapshot',
        'pos_base_unit_name', 'pos_sync_status', 'pos_item_updated_at', 'last_pos_sync_at',
    ];

    protected $casts = [
        'images'               => 'array',
        'variants'             => 'array',
        'attributes'           => 'array',
        'related_products'     => 'array',
        'status'               => 'boolean',
        'is_new'               => 'boolean',
        'is_featured'          => 'boolean',
        'visible_individually' => 'boolean',
        'show_stock_level'     => 'boolean',
        'special_price_from'   => 'datetime',
        'special_price_to'     => 'datetime',
        'expiry_date'          => 'date',
        'pos_item_updated_at'  => 'datetime',
        'last_pos_sync_at'     => 'datetime',
    ];

    protected $appends = [
        'is_on_flash_sale',
        'is_on_sale',
        'effective_price',
        'expiry_status',
        'days_until_expiry',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function posUnits(): HasMany
    {
        return $this->hasMany(ProductPosUnit::class)->orderBy('sort_order');
    }

    public function variantMappings(): HasMany
    {
        return $this->hasMany(ProductVariantMapping::class)->orderBy('sort_order');
    }

    public function syncedPosItem(): BelongsTo
    {
        return $this->belongsTo(SyncedPosItem::class, 'pos_item_id', 'pos_item_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function approvedReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->where('is_approved', true);
    }

    public function getAverageRatingAttribute(): float
    {
        return (float) ($this->approvedReviews()->avg('rating') ?: 5.0);
    }

    /**
     * Check if product is linked to POS.
     */
    public function isPosLinked(): bool
    {
        return $this->inventory_source === 'pos' && !empty($this->pos_item_id);
    }

    /**
     * Check if product is available for customer purchase.
     */
    public function isAvailableForPurchase(): bool
    {
        if (!$this->status) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        if ($this->isPosLinked()) {
            if (in_array($this->pos_sync_status, ['missing_pos_item', 'inactive_pos_item', 'mapping_sync_failed'], true)) {
                return false;
            }
        }

        return $this->stock > 0;
    }

    /**
     * Scope query to products visible/active on the storefront.
     */
    public function scopeAvailableOnStorefront($query)
    {
        $query->where('status', true);

        if (static::isExpiryTrackingEnabled() && StoreSetting::getBool('hide_expired_products_on_storefront', true)) {
            $query->where(function ($q) {
                $q->whereNull('expiry_date')
                  ->orWhere('expiry_date', '>', now()->toDateString());
            });
        }

        return $query;
    }

    /**
     * Check if product is currently within an active Flash Sale window.
     */
    public function getIsOnFlashSaleAttribute(): bool
    {
        return !empty($this->special_price) &&
            (!$this->special_price_from || now()->gte($this->special_price_from)) &&
            (!$this->special_price_to   || now()->lte($this->special_price_to));
    }

    /**
     * Check if product has any active price discount (Flash Sale or standard markdown).
     */
    public function getIsOnSaleAttribute(): bool
    {
        return $this->is_on_flash_sale || (!empty($this->discount_price) && (float) $this->discount_price < (float) $this->price);
    }

    /**
     * Get effective price for a specific POS unit or base product.
     */
    public function getEffectivePriceForUnit(?ProductPosUnit $unit = null): float
    {
        if ($unit && $unit->unit_kind !== 'base' && $unit->synced_standard_price > 0) {
            return (float) $unit->synced_standard_price;
        }

        return $this->getEffectivePriceAttribute();
    }

    /**
     * Get the effective current price (respects hierarchy: Flash Sale -> Regular Discount -> Base Price).
     */
    public function getEffectivePriceAttribute(): float
    {
        if ($this->is_on_flash_sale) {
            return (float) $this->special_price;
        }
        return (!empty($this->discount_price) && (float) $this->discount_price < (float) $this->price)
            ? (float) $this->discount_price
            : (float) $this->price;
    }

    /**
     * Check if product expiry tracking is globally enabled in Store Settings.
     */
    public static function isExpiryTrackingEnabled(): bool
    {
        return StoreSetting::getBool('enable_product_expiry_tracking', false);
    }

    /**
     * Check if this product has expired.
     */
    public function isExpired(): bool
    {
        if (!static::isExpiryTrackingEnabled() || empty($this->expiry_date)) {
            return false;
        }

        return $this->expiry_date->isPast() && !$this->expiry_date->isToday();
    }

    /**
     * Check if this product is nearing its expiry date within the warning threshold.
     */
    public function isExpiringSoon(?int $thresholdDays = null): bool
    {
        if (!static::isExpiryTrackingEnabled() || empty($this->expiry_date) || $this->isExpired()) {
            return false;
        }

        $threshold = $thresholdDays ?? (int) StoreSetting::get('expiry_warning_threshold_days', 30);
        $diff = now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false);

        return $diff >= 0 && $diff <= $threshold;
    }

    /**
     * Number of days until product expires (negative if already expired, null if unset).
     */
    public function daysUntilExpiry(): ?int
    {
        if (empty($this->expiry_date)) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false);
    }

    /**
     * Get evaluated expiry status attribute: 'expired' | 'expiring_soon' | 'valid' | 'none'.
     */
    public function getExpiryStatusAttribute(): string
    {
        if (!static::isExpiryTrackingEnabled() || empty($this->expiry_date)) {
            return 'none';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        if ($this->isExpiringSoon()) {
            return 'expiring_soon';
        }

        return 'valid';
    }

    /**
     * Get computed days remaining attribute.
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        return $this->daysUntilExpiry();
    }
}
