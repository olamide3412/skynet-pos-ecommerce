<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchLog extends Model
{
    protected $fillable = [
        'query',
        'normalized_query',
        'results_count',
        'customer_id',
        'ip_address',
        'user_agent',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Helper to log a search event cleanly.
     */
    public static function record(string $query, int $resultsCount = 0, ?int $customerId = null, ?string $ip = null, ?string $userAgent = null): ?self
    {
        $raw = trim($query);
        if ($raw === '' || mb_strlen($raw) < 2) {
            return null;
        }

        $normalized = mb_strtolower($raw);

        return static::create([
            'query'            => mb_substr($raw, 0, 255),
            'normalized_query' => mb_substr($normalized, 0, 255),
            'results_count'    => $resultsCount,
            'customer_id'      => $customerId,
            'ip_address'       => $ip,
            'user_agent'       => $userAgent ? mb_substr($userAgent, 0, 255) : null,
        ]);
    }
}

