<?php

namespace BadCookies\SocialLogin\Controllers;

use Exception;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cookie;
use BadCookies\SocialLogin\Models\Settings;
use BadCookies\SocialLogin\Services\OAuthService;

class OAuthController extends Controller
{
    protected OAuthService $oauthService;

    public function __construct()
    {
        $this->oauthService = new OAuthService();
    }

    public function redirect(string $provider)
    {
        if (!$this->isProviderEnabled($provider)) {
            return redirect('/')->withErrors(['Dostawca OAuth "' . $provider . '" jest wyłączony lub nieznany.']);
        }

        try {
            $url = $this->oauthService->getRedirectUrl($provider);
            return redirect($url);
        } catch (Exception $e) {
            \Log::error('[BadCookies.SocialLogin] Redirect error: ' . $e->getMessage());
            return redirect('/')->withErrors(['Błąd uruchamiania OAuth: ' . $e->getMessage()]);
        }
    }

    public function callback(string $provider)
    {
        if (!$this->isProviderEnabled($provider)) {
            return redirect('/')->withErrors(['Dostawca OAuth "' . $provider . '" jest wyłączony.']);
        }

        $code  = request('code');
        $state = request('state');
        $error = request('error');

        if ($error) {
            return redirect('/')->withErrors(['Logowanie przez ' . ucfirst($provider) . ' zostało anulowane.']);
        }

        if (!$code || !$state) {
            return redirect('/')->withErrors(['Brak wymaganych parametrów OAuth.']);
        }

        try {
            $userData = $this->oauthService->handleCallback($provider, $code, $state);
            $intent   = request()->cookie('badcookies_oauth_intent');

            // Jeśli tryb linkowania – przekaż przez ciasteczko do strony CMS
            if ($intent === 'link') {
                $payload = encrypt(json_encode([
                    'mode'     => 'link',
                    'provider' => $provider,
                    'data'     => $userData,
                ]));

                return redirect(Settings::getAuthPageUrl())
                    ->withCookie(cookie('badcookies_oauth_payload', $payload, 5));
            }

            // Tryb logowania/rejestracji
            $user = $this->oauthService->loginOrRegister($provider, $userData);

            // Przekaż user_id do strony CMS przez krótkotrwałe zaszyfrowane ciasteczko (5 min)
            $payload = encrypt(json_encode([
                'mode'    => 'login',
                'user_id' => $user->id,
            ]));

            return redirect(Settings::getAuthPageUrl())
                ->withCookie(cookie('badcookies_oauth_payload', $payload, 5));

        } catch (Exception $e) {
            \Log::error('[BadCookies.SocialLogin] EXCEPTION: ' . $e->getMessage());
            \Log::error('[BadCookies.SocialLogin] Trace: ' . $e->getTraceAsString());
            return redirect('/')->withErrors(['Błąd logowania: ' . $e->getMessage()]);
        }
    }

    private function isProviderEnabled(string $provider): bool
    {
        return match ($provider) {
            'google'   => Settings::isGoogleEnabled(),
            'facebook' => Settings::isFacebookEnabled(),
            default    => false,
        };
    }
}
