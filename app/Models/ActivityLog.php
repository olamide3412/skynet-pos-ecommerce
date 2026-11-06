<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'actor_type',
        'actor_name',
        'action_type',
        'description',
        'details',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'details'    => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope filter by actor (specific user id or 'system').
     */
    public function scopeForActor(Builder $query, ?string $actor): Builder
    {
        if (empty($actor)) {
            return $query;
        }

        if ($actor === 'system') {
            return $query->where(function ($q) {
                $q->whereNull('user_id')->orWhere('actor_type', 'system');
            });
        }

        return $query->where('user_id', (int) $actor);
    }

    /**
     * Scope filter by category action_type.
     */
    public function scopeForActionType(Builder $query, ?string $actionType): Builder
    {
        if (empty($actionType)) {
            return $query;
        }

        return $query->where('action_type', $actionType);
    }

    /**
     * Scope filter by date range.
     */
    public function scopeDateRange(Builder $query, ?string $startDate, ?string $endDate): Builder
    {
        if (!empty($startDate)) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if (!empty($endDate)) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope search query across description, IP, actor_name, and user credentials.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        $term = '%' . trim($search) . '%';

        return $query->where(function ($q) use ($term) {
            $q->where('description', 'like', $term)
              ->orWhere('ip_address', 'like', $term)
              ->orWhere('actor_name', 'like', $term)
              ->orWhere('action_type', 'like', $term)
              ->orWhereHas('user', function ($uq) use ($term) {
                  $uq->where('name', 'like', $term)
                     ->orWhere('email', 'like', $term);
              });
        });
    }
}

