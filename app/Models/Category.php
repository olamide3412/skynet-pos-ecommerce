<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id', 'name', 'slug', 'description', 'image',
        'visible_in_menu', 'menu_position',
    ];

    protected $casts = [
        'parent_id'       => 'integer',
        'visible_in_menu' => 'boolean',
        'menu_position'   => 'integer',
    ];

    /** Parent category (null = this IS a parent) */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** Subcategories under this parent */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('menu_position');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Filterable attributes selected for this category */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class);
    }
}
