<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'state',
        'city',
        'fee',
        'estimated_days',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'fee'        => 'decimal:2',
        'is_active'  => 'boolean',
        'is_default' => 'boolean',
    ];

    protected $appends = [
        'rate',
        'delivery_estimate_days',
    ];

    public function getRateAttribute()
    {
        return $this->fee;
    }

    public function getDeliveryEstimateDaysAttribute()
    {
        return $this->estimated_days;
    }
}
