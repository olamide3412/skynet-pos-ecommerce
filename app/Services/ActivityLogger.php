<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class ActivityLogger
{
    /**
     * Primary logging method. Safe and exception-resilient.
     */
    public static function log(
        string $actionType,
        string $description,
        ?array $details = null,
        ?int $userId = null,
        ?string $actorType = null,
        ?string $actorName = null
    ): ?ActivityLog {
        try {
            $user = auth('web')->user();
            $resolvedUserId = $userId !== null ? $userId : $user?->id;

            if ($resolvedUserId && !$actorType) {
                if ($user && $user->id === $resolvedUserId) {
                    $actorType = in_array($user->role, ['super administrator', 'administrator'], true) ? 'admin' : 'staff';
                    $actorName = $actorName ?? $user->name;
                } else {
                    $actorType = 'admin';
                    $actorName = $actorName ?? ('User #' . $resolvedUserId);
                }
            } elseif (!$resolvedUserId) {
                $actorType = $actorType ?? 'system';
                $actorName = $actorName ?? 'System Bot';
            } else {
                $actorName = $actorName ?? ($user?->name ?? ('User #' . $resolvedUserId));
            }

            $req = request();
            $ipAddress = $req ? $req->ip() : null;
            $userAgent = $req ? substr($req->userAgent() ?? '', 0, 500) : null;

            return ActivityLog::create([
                'user_id'     => $resolvedUserId,
                'actor_type'  => $actorType,
                'actor_name'  => $actorName,
                'action_type' => $actionType,
                'description' => substr($description, 0, 500),
                'details'     => $details,
                'ip_address'  => $ipAddress,
                'user_agent'  => $userAgent,
                'created_at'  => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('ActivityLogger exception: ' . $e->getMessage(), [
                'exception'   => $e,
                'action_type' => $actionType,
                'description' => $description,
            ]);
            return null;
        }
    }

    public static function auth(string $message, ?array $details = null, ?int $userId = null): ?ActivityLog
    {
        return self::log('auth', $message, $details, $userId);
    }

    public static function product(string $message, ?array $details = null): ?ActivityLog
    {
        return self::log('product', $message, $details);
    }

    public static function order(string $message, ?array $details = null): ?ActivityLog
    {
        return self::log('order', $message, $details);
    }

    public static function customer(string $message, ?array $details = null, ?int $userId = null, ?string $actorType = null): ?ActivityLog
    {
        return self::log('customer', $message, $details, $userId, $actorType);
    }

    public static function setting(string $message, ?array $details = null): ?ActivityLog
    {
        return self::log('setting', $message, $details);
    }

    public static function affiliate(string $message, ?array $details = null): ?ActivityLog
    {
        return self::log('affiliate', $message, $details);
    }

    public static function pos(string $message, ?array $details = null): ?ActivityLog
    {
        return self::log('pos_sync', $message, $details);
    }

    public static function userAction(string $message, ?array $details = null): ?ActivityLog
    {
        return self::log('user_management', $message, $details);
    }

    public static function system(string $message, ?array $details = null): ?ActivityLog
    {
        return self::log('system', $message, $details, null, 'system');
    }

    public static function webhook(string $message, ?array $details = null, string $provider = 'Payment Webhook'): ?ActivityLog
    {
        return self::log('webhook', $message, $details, null, 'system', $provider);
    }
}

