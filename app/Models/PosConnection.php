<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PosConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'pos_base_url',
        'pos_integration_id',
        'pos_branch_id',
        'pos_branch_name',
        'client_id',
        'encrypted_api_secret',
        'is_connected',
        'connected_at',
        'last_successful_request_at',
        'last_catalog_sync_at',
        'last_inventory_sync_at',
        'last_error_at',
        'last_error_message',
    ];

    protected $casts = [
        'is_connected'               => 'boolean',
        'connected_at'               => 'datetime',
        'last_successful_request_at' => 'datetime',
        'last_catalog_sync_at'       => 'datetime',
        'last_inventory_sync_at'     => 'datetime',
        'last_error_at'              => 'datetime',
    ];

    protected $hidden = [
        'encrypted_api_secret',
    ];

    /**
     * Get the active POS connection (singleton).
     */
    public static function active(): ?self
    {
        return self::where('is_connected', true)->latest()->first() ?: self::latest()->first();
    }

    /**
     * Set encrypted API secret.
     */
    public function setApiSecretAttribute(?string $value): void
    {
        $this->attributes['encrypted_api_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Get decrypted API secret.
     */
    public function getDecryptedApiSecret(): ?string
    {
        if (empty($this->encrypted_api_secret)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->encrypted_api_secret);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
