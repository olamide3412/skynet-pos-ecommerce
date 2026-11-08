<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key, with an optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Get a boolean setting value by key.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $val = static::get($key, null);
        if ($val === null) {
            return $default;
        }
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set a setting value by key (create or update).
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Get all settings as a key=>value array.
     */
    public static function allAsArray(): array
    {
        return static::all()->pluck('value', 'key')->toArray();
    }

    /**
     * Dynamically resolve the store's public base URL.
     * Prevents static localhost links in outgoing emails and queued background workers.
     */
    public static function getBaseUrl(?string $path = null): string
    {
        $base = null;

        // 1. Explicit site_url configured in StoreSetting
        $configured = static::get('site_url');
        if (!empty($configured) && filter_var($configured, FILTER_VALIDATE_URL)) {
            $base = rtrim($configured, '/');
        }

        // 2. Incoming HTTP request domain (if available and not localhost)
        if (!$base) {
            try {
                if (request() && !in_array(request()->getHost(), ['localhost', '127.0.0.1', '::1'], true)) {
                    $base = rtrim(request()->schemeAndHttpHost(), '/');
                }
            } catch (\Throwable $e) {
                // Request context unavailable
            }
        }

        // 3. config('app.url') if not localhost
        if (!$base) {
            $appUrl = config('app.url');
            if (!empty($appUrl) && !str_contains($appUrl, 'localhost') && !str_contains($appUrl, '127.0.0.1')) {
                $base = rtrim($appUrl, '/');
            }
        }

        // 4. Default fallback using url()
        if (!$base) {
            return url($path ?? '/');
        }

        return $path ? ($base . '/' . ltrim($path, '/')) : $base;
    }

    /**
     * Resolve the absolute local file path of the company logo on disk.
     */
    public static function getLogoPath(): ?string
    {
        $rawLogo = static::get('company_logo', null);
        if ($rawLogo) {
            $clean = ltrim(preg_replace('#^/?(storage/)?#', '', $rawLogo), '/');
            $storagePath = storage_path('app/public/' . $clean);
            if (file_exists($storagePath)) {
                return $storagePath;
            }
            $publicPath = public_path('storage/' . $clean);
            if (file_exists($publicPath)) {
                return $publicPath;
            }
            return null;
        }

        if (file_exists(public_path('images/logo.png'))) {
            return public_path('images/logo.png');
        }

        return null;
    }

    /**
     * Dynamically resolve the absolute public URL of the company logo.
     */
    public static function getLogoUrl(): ?string
    {
        $rawLogo = static::get('company_logo', null);
        if ($rawLogo) {
            if (\Illuminate\Support\Str::startsWith($rawLogo, ['http://', 'https://'])) {
                return $rawLogo;
            }
            $clean = ltrim(preg_replace('#^/?(storage/)?#', '', $rawLogo), '/');
            return static::getBaseUrl('storage/' . $clean);
        }

        if (file_exists(public_path('images/logo.png'))) {
            return static::getBaseUrl('images/logo.png');
        }

        return null;
    }
}
