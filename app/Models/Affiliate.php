<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Affiliate extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'referral_code',
        'status',
        'commission_type',
        'commission_rate',
        'min_order_amount',
        'bank_name',
        'account_number',
        'account_name',
        'wallet_balance',
        'total_earned',
        'total_withdrawn',
        'application_note',
        'admin_notes',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'commission_rate'  => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'wallet_balance'   => 'decimal:2',
        'total_earned'     => 'decimal:2',
        'total_withdrawn'  => 'decimal:2',
        'approved_at'      => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(AffiliateWalletTransaction::class)->latest('id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(AffiliateWithdrawal::class)->latest('id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(AffiliateReferralVisit::class)->latest('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function getReferralUrlAttribute(): string
    {
        return url('/?ref=' . $this->referral_code);
    }

    /**
     * Generate a unique uppercase referral code.
     */
    public static function generateUniqueCode(string $namePrefix = ''): string
    {
        $cleanPrefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $namePrefix));
        $prefix = substr($cleanPrefix, 0, 4) ?: 'SKY';

        do {
            $code = $prefix . mt_rand(1000, 9999);
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }
}

