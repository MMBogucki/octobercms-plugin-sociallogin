<?php

namespace BadCookies\SocialLogin\Models;

use Model;
use Cms\Classes\Page;
use Cms\Classes\Theme;

class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode   = 'badcookies_sociallogin_settings';
    public $settingsFields = '$/badcookies/sociallogin/models/settings/fields.yaml';

    public static function getGoogleClientId(): string
    {
        return (string) self::get('google_client_id', '');
    }

    public static function getGoogleClientSecret(): string
    {
        return (string) self::get('google_client_secret', '');
    }

    public static function getFacebookClientId(): string
    {
        return (string) self::get('facebook_client_id', '');
    }

    public static function getFacebookClientSecret(): string
    {
        return (string) self::get('facebook_client_secret', '');
    }

    public static function isGoogleEnabled(): bool
    {
        return (bool) self::get('google_enabled', false);
    }

    public static function isFacebookEnabled(): bool
    {
        return (bool) self::get('facebook_enabled', false);
    }

    public static function getRedirectUrl(): string
    {
        return self::resolvePageUrl('redirect_url', '/');
    }

    public static function getAuthPageUrl(): string
    {
        return self::resolvePageUrl('auth_page_url', '/social-auth');
    }

    // ── Helpers ────────────────────────────────

    /**
     * Resolves a pagefinder URI to a proper URL.
     *
     * pagefinder returns a special OctoberCMS URI format:
     * october://cms-page@link/account/social-auth?title=...
     *
     * We extract the path part (e.g. "account/social-auth"),
     * load the CMS page by that filename and return its URL.
     */
    private static function resolvePageUrl(string $key, string $default): string
    {
        $value = (string) self::get($key, '');

        if (empty($value)) {
            return $default;
        }

        // Already a plain URL (starts with /)
        if (str_starts_with($value, '/')) {
            return $value;
        }

        // Parse october://cms-page@link/{filename}?title=...
        // Extract the path between "link/" and "?"
        if (str_contains($value, 'cms-page@link/')) {
            $path = preg_replace('/^.*cms-page@link\/([^?]+).*$/', '$1', $value);

            if ($path && $path !== $value) {
                try {
                    $theme = Theme::getActiveTheme();
                    $page  = Page::load($theme, $path);
                    if ($page && !empty($page->url)) {
                        return $page->url;
                    }
                } catch (\Exception $e) {
                    // Fall through to path-based fallback
                }

                // Use the extracted path as URL directly
                return '/' . ltrim($path, '/');
            }
        }

        return '/' . ltrim($value, '/');
    }
}
