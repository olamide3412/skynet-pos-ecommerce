<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'status',
        'reference',
        'message',
        'context',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'context'      => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Create a sanitized log entry (never stores secrets).
     */
    public static function log(string $type, string $status, ?string $message = null, ?string $reference = null, ?array $context = null): self
    {
        $sanitizedContext = null;
        if ($context) {
            $sanitizedContext = self::sanitize($context);
        }

        return self::create([
            'type'         => $type,
            'status'       => $status,
            'reference'    => $reference,
            'message'      => $message,
            'context'      => $sanitizedContext,
            'started_at'   => now(),
            'completed_at' => now(),
        ]);
    }

    protected static function sanitize(array $data): array
    {
        $sensitiveKeys = ['secret', 'token', 'password', 'api_secret', 'encrypted_api_secret', 'authorization', 'key'];
        $clean = [];

        foreach ($data as $k => $v) {
            if (is_string($k) && in_array(strtolower($k), $sensitiveKeys, true)) {
                $clean[$k] = '***REDACTED***';
            } elseif (is_array($v)) {
                $clean[$k] = self::sanitize($v);
            } else {
                $clean[$k] = $v;
            }
        }

        return $clean;
    }
}
