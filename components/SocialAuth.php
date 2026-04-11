<?php

namespace BadCookies\SocialLogin\Components;

use Cms\Classes\ComponentBase;
use RainLab\User\Models\User;
use BadCookies\SocialLogin\Models\Settings;
use BadCookies\SocialLogin\Services\OAuthService;

class SocialAuth extends ComponentBase
{
    public function componentDetails(): array
    {
        return [
            'name'        => 'Social Auth Handler',
            'description' => 'Internal component that handles OAuth session login. Place on the /social-auth page.',
        ];
    }

    public function defineProperties(): array
    {
        return [];
    }

    public function onRun()
    {
        \Log::info('[BadCookies.SocialLogin] SocialAuth::onRun fired');

        $cookieValue = $this->getRawCookie('badcookies_oauth_payload');

        \Log::info('[BadCookies.SocialLogin] badcookies_oauth_payload present: ' . ($cookieValue ? 'YES' : 'NO'));

        if (!$cookieValue) {
            \Log::warning('[BadCookies.SocialLogin] No payload cookie – redirecting to /');
            return redirect('/');
        }

        try {
            // Odszyfruj ciasteczko – może być zaszyfrowane przez Laravel EncryptCookies
            $decrypted = $this->decryptCookie($cookieValue);
            $payload   = json_decode($decrypted, true);

            \Log::info('[BadCookies.SocialLogin] Payload mode: ' . ($payload['mode'] ?? 'unknown'));

            if (empty($payload['mode'])) {
                throw new \Exception('Invalid OAuth payload.');
            }

            // Usuń ciasteczka
            \Cookie::queue(\Cookie::forget('badcookies_oauth_payload'));
            \Cookie::queue(\Cookie::forget('badcookies_oauth_intent'));

            if ($payload['mode'] === 'login') {
                $user = User::find($payload['user_id']);
                \Log::info('[BadCookies.SocialLogin] User found: ' . ($user ? 'YES id=' . $user->id : 'NO'));

                if (!$user) {
                    throw new \Exception('User not found.');
                }

                app('auth')->login($user, true);

                $check = app('auth')->getUser();
                \Log::info('[BadCookies.SocialLogin] Auth after login: ' . ($check ? 'id=' . $check->id : 'NULL'));

                return redirect(Settings::getRedirectUrl())
                    ->with('badcookies_social_success', 'Successfully signed in.');
            }

            if ($payload['mode'] === 'link') {
                $loggedUser = app('auth')->getUser();

                if (!$loggedUser) {
                    throw new \Exception('You must be logged in to link an account.');
                }

                $oauthService = new OAuthService();
                $oauthService->linkToExistingUser($loggedUser, $payload['provider'], $payload['data']);

                return redirect(Settings::getRedirectUrl())
                    ->with('badcookies_social_success', ucfirst($payload['provider']) . ' account linked successfully.');
            }

        } catch (\Exception $e) {
            \Log::error('[BadCookies.SocialLogin] SocialAuth error: ' . $e->getMessage());
            return redirect('/')->withErrors(['Login error: ' . $e->getMessage()]);
        }

        return redirect('/');
    }

    // ── Helpers ────────────────────────────────

    /**
     * Odczytuje surową wartość ciasteczka bezpośrednio z $_COOKIE,
     * omijając middleware który może je modyfikować lub ukrywać.
     */
    private function getRawCookie(string $name): ?string
    {
        // Metoda 1: Laravel request (po odszyfrowaniu przez middleware)
        $value = request()->cookie($name);
        if ($value) {
            return $value;
        }

        // Metoda 2: Bezpośrednio z $_COOKIE (surowa, zaszyfrowana wartość)
        return $_COOKIE[$name] ?? null;
    }

    /**
     * Odszyfrowuje wartość ciasteczka.
     * Jeśli ciasteczko zostało już odszyfrowane przez middleware – zwraca wprost.
     * Jeśli jest surowe (zaszyfrowane przez Laravel) – odszyfrowuje.
     */
    private function decryptCookie(string $value): string
    {
        // Sprawdź czy to już odszyfrowany JSON (zaczyna się od '{')
        $trimmed = trim($value);
        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '"')) {
            return $trimmed;
        }

        // Spróbuj odszyfrować przez Laravel
        try {
            return decrypt($value);
        } catch (\Exception $e) {
            // Może być zakodowane URL – spróbuj zdekodować najpierw
            $decoded = urldecode($value);
            return decrypt($decoded);
        }
    }
}
