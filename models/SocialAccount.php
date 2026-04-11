<?php

namespace BadCookies\SocialLogin\Models;

use Model;

/**
 * SocialAccount – łączy konto RainLab.User z dostawcą OAuth.
 *
 * Kolumny tabeli: id, user_id, provider, provider_user_id, token, created_at, updated_at
 */
class SocialAccount extends Model
{
    public $table = 'badcookies_sociallogin_accounts';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'token',
    ];

    public $timestamps = true;

    // ──────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────

    public $belongsTo = [
        'user' => [\RainLab\User\Models\User::class, 'key' => 'user_id'],
    ];

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    /**
     * Finds an existing social account record by provider + provider user id.
     */
    public static function findByProvider(string $provider, string $providerUserId): ?self
    {
        return self::where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();
    }
}
