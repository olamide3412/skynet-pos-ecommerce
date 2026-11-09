<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'description',
        'is_active',
        'status',
        'is_featured',
        'sort_order',
        'pos_brand_id',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'is_featured'  => 'boolean',
        'sort_order'   => 'integer',
        'pos_brand_id' => 'integer',
    ];

    protected $appends = [
        'logo_url',
    ];

    protected static function booted(): void
    {
        static::saving(function (Brand $brand) {
            if (empty($brand->slug)) {
                $baseSlug = Str::slug($brand->name) ?: 'brand-' . Str::lower(Str::random(6));
                $slug = $baseSlug;
                $counter = 2;

                while (static::where('slug', $slug)
                    ->when($brand->exists, fn (Builder $q) => $q->where('id', '!=', $brand->id))
                    ->exists()
                ) {
                    $slug = "{$baseSlug}-{$counter}";
                    $counter++;
                }

                $brand->slug = $slug;
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function getStatusAttribute(): bool
    {
        return (bool) ($this->attributes['is_active'] ?? false);
    }

    public function setStatusAttribute($value): void
    {
        $this->attributes['is_active'] = (bool) $value;
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (empty($this->logo)) {
            return null;
        }

        if (str_starts_with($this->logo, 'http://') || str_starts_with($this->logo, 'https://') || str_starts_with($this->logo, '/storage/')) {
            return $this->logo;
        }

        return Storage::disk('public')->url($this->logo);
    }
}

