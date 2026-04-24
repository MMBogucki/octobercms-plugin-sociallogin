<?php

namespace BadCookies\SocialLogin\Models;

use Model;
use Cms\Classes\Theme;
use Cms\Classes\Page;

class Settings extends Model
{
    use \System\Traits\ConfigMaker;

    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode   = 'badcookies_sociallogin_settings';
    public $settingsFields = 'fields.yaml';

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
        if (str_contains($value, 'cms-page@link/')) {
            $path = preg_replace('/^.*cms-page@link\/([^?]+).*$/', '$1', $value);

            if ($path && $path !== $value) {
                try {
                    $theme = Theme::getActiveTheme();
                    if ($theme) {
                        $page = Page::load($theme, $path);
                        if ($page && !empty($page->url)) {
                            return $page->url;
                        }
                    }
                    // Use the extracted path as URL directly
                    return '/' . ltrim($path, '/');
                } catch (\Exception $e) {
                    return '/' . ltrim($path, '/');
                }
            }
        }

        return '/' . ltrim($value, '/');
    }
}
