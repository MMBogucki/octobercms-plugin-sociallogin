<?php

namespace BadCookies\SocialLogin\Services;

use Exception;
use RainLab\User\Models\User;
use System\Models\File;
use BadCookies\SocialLogin\Models\Settings;
use BadCookies\SocialLogin\Models\SocialAccount;

class OAuthService
{
    private const PROVIDERS = [
        'google' => [
            'auth_url'  => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'user_url'  => 'https://www.googleapis.com/oauth2/v3/userinfo',
            'scope'     => 'openid email profile',
        ],
        'facebook' => [
            'auth_url'  => 'https://www.facebook.com/v19.0/dialog/oauth',
            'token_url' => 'https://graph.facebook.com/v19.0/oauth/access_token',
            'user_url'  => 'https://graph.facebook.com/me?fields=id,name,email,first_name,last_name,picture.width(400)',
            'scope'     => 'email public_profile',
        ],
    ];

    public function getRedirectUrl(string $provider): string
    {
        $config = $this->getConfig($provider);
        $state  = $this->generateState($provider);

        $params = http_build_query([
            'client_id'     => $this->getClientId($provider),
            'redirect_uri'  => $this->getCallbackUrl($provider),
            'response_type' => 'code',
            'scope'         => $config['scope'],
            'state'         => $state,
        ]);

        return $config['auth_url'] . '?' . $params;
    }

    public function handleCallback(string $provider, string $code, string $state): array
    {
        $this->validateState($provider, $state);
        $token    = $this->exchangeCodeForToken($provider, $code);
        $userData = $this->fetchUserData($provider, $token);
        return $userData;
    }

    public function loginOrRegister(string $provider, array $userData): User
    {
        $providerUserId = (string) ($userData['id'] ?? $userData['sub'] ?? '');
        $email          = $userData['email'] ?? null;

        if (empty($providerUserId)) {
            throw new Exception('Nie udało się pobrać ID użytkownika od dostawcy OAuth.');
        }

        $socialAccount = SocialAccount::findByProvider($provider, $providerUserId);

        if ($socialAccount) {
            $user = $socialAccount->user;
            if (!$user) {
                throw new Exception('Konto społecznościowe istnieje, ale nie znaleziono użytkownika.');
            }
            $this->updateToken($socialAccount, $userData['access_token'] ?? null);

            // Pobierz avatar jeśli użytkownik jeszcze go nie ma
            if (!$user->avatar) {
                $this->downloadAvatar($user, $provider, $userData);
            }

            return $user;
        }

        $user = null;
        if ($email) {
            $user = User::where('email', $email)->first();
        }

        if (!$user) {
            $user = $this->registerUser($userData, $provider);
        }

        $this->linkAccount($user, $provider, $providerUserId, $userData['access_token'] ?? null);

        // Pobierz avatar po rejestracji/połączeniu konta
        if (!$user->avatar) {
            $this->downloadAvatar($user, $provider, $userData);
        }

        return $user;
    }

    public function linkToExistingUser(User $user, string $provider, array $userData): void
    {
        $providerUserId = (string) ($userData['id'] ?? $userData['sub'] ?? '');

        if (empty($providerUserId)) {
            throw new Exception('Nie udało się pobrać ID użytkownika od dostawcy OAuth.');
        }

        $existing = SocialAccount::findByProvider($provider, $providerUserId);

        if ($existing && $existing->user_id !== $user->id) {
            throw new Exception('To konto ' . ucfirst($provider) . ' jest już powiązane z innym użytkownikiem.');
        }

        if (!$existing) {
            $this->linkAccount($user, $provider, $providerUserId, $userData['access_token'] ?? null);
        }

        // Pobierz avatar jeśli użytkownik jeszcze go nie ma
        if (!$user->avatar) {
            $this->downloadAvatar($user, $provider, $userData);
        }
    }

    // ── Avatar ────────────────────────────────

    /**
     * Pobiera URL avatara z danych dostawcy OAuth,
     * ściąga obraz i zapisuje go jako avatar użytkownika RainLab.User.
     */
    private function downloadAvatar(User $user, string $provider, array $userData): void
    {
        try {
            $avatarUrl = $this->getAvatarUrl($provider, $userData);

            if (!$avatarUrl) {
                return;
            }

            // Pobierz obraz przez cURL
            $imageData = $this->downloadImage($avatarUrl);

            if (!$imageData) {
                \Log::warning('[BadCookies.SocialLogin] Nie udało się pobrać avatara z: ' . $avatarUrl);
                return;
            }

            // Zapisz do pliku tymczasowego
            $tmpFile  = tempnam(sys_get_temp_dir(), 'badcookies_avatar_') . '.jpg';
            file_put_contents($tmpFile, $imageData);

            // Usuń poprzedni avatar jeśli istnieje
            if ($user->avatar) {
                $user->avatar()->delete();
            }

            // Utwórz obiekt File OctoberCMS i przypisz do usera
            $file = new File;
            $file->fromFile($tmpFile);
            $file->save();

            $user->avatar()->add($file);

            // Usuń plik tymczasowy
            @unlink($tmpFile);

            \Log::info('[BadCookies.SocialLogin] Avatar pobrany dla użytkownika id=' . $user->id);

        } catch (\Exception $e) {
            // Avatar to funkcja opcjonalna – nie przerywamy rejestracji w razie błędu
            \Log::warning('[BadCookies.SocialLogin] Błąd pobierania avatara: ' . $e->getMessage());
        }
    }

    /**
     * Wyciąga URL avatara z danych zwróconych przez dostawcę OAuth.
     */
    private function getAvatarUrl(string $provider, array $userData): ?string
    {
        return match ($provider) {
            // Google zwraca "picture" jako bezpośredni URL
            'google' => $userData['picture'] ?? null,

            // Facebook zwraca zagnieżdżony obiekt "picture"
            'facebook' => $userData['picture']['data']['url']
                          ?? $userData['picture']
                          ?? null,

            default => null,
        };
    }

    /**
     * Pobiera plik obrazu przez cURL i zwraca jego zawartość binarną.
     */
    private function downloadImage(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'BadCookies.SocialLogin/1.0',
        ]);

        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($data && $code === 200) ? $data : null;
    }

    // ── State HMAC ─────────────────────────────

    private function generateState(string $provider): string
    {
        $nonce = bin2hex(random_bytes(16));
        return $nonce . '.' . $this->signState($nonce, $provider);
    }

    private function validateState(string $provider, string $state): void
    {
        $parts = explode('.', $state, 2);

        if (count($parts) !== 2) {
            throw new Exception('Nieprawidłowy format parametru state.');
        }

        [$nonce, $receivedSig] = $parts;

        if (!hash_equals($this->signState($nonce, $provider), $receivedSig)) {
            throw new Exception('Nieprawidłowy parametr state – możliwy atak CSRF.');
        }
    }

    private function signState(string $nonce, string $provider): string
    {
        $secret = config('app.key', 'fallback-secret');
        if (str_starts_with($secret, 'base64:')) {
            $secret = base64_decode(substr($secret, 7));
        }
        return hash_hmac('sha256', $nonce . '|' . $provider, $secret);
    }

    // ── Helpers ────────────────────────────────

    private function getConfig(string $provider): array
    {
        if (!array_key_exists($provider, self::PROVIDERS)) {
            throw new Exception("Nieznany dostawca OAuth: {$provider}");
        }
        return self::PROVIDERS[$provider];
    }

    private function getClientId(string $provider): string
    {
        return match ($provider) {
            'google'   => Settings::getGoogleClientId(),
            'facebook' => Settings::getFacebookClientId(),
            default    => throw new Exception("Brak Client ID dla: {$provider}"),
        };
    }

    private function getClientSecret(string $provider): string
    {
        return match ($provider) {
            'google'   => Settings::getGoogleClientSecret(),
            'facebook' => Settings::getFacebookClientSecret(),
            default    => throw new Exception("Brak Client Secret dla: {$provider}"),
        };
    }

    private function getCallbackUrl(string $provider): string
    {
        return url('/callback/' . $provider);
    }

    private function exchangeCodeForToken(string $provider, string $code): string
    {
        $config   = $this->getConfig($provider);
        $postData = http_build_query([
            'code'          => $code,
            'client_id'     => $this->getClientId($provider),
            'client_secret' => $this->getClientSecret($provider),
            'redirect_uri'  => $this->getCallbackUrl($provider),
            'grant_type'    => 'authorization_code',
        ]);

        $response = $this->httpPost($config['token_url'], $postData);
        $data     = json_decode($response, true);

        if (empty($data['access_token'])) {
            throw new Exception('Nie udało się uzyskać access token. Odpowiedź: ' . $response);
        }

        return $data['access_token'];
    }

    private function fetchUserData(string $provider, string $token): array
    {
        $config   = $this->getConfig($provider);
        $response = $this->httpGet($config['user_url'], $token);
        $data     = json_decode($response, true);

        if (empty($data)) {
            throw new Exception('Nie udało się pobrać danych użytkownika od dostawcy OAuth.');
        }

        $data['access_token'] = $token;
        return $data;
    }

    private function httpPost(string $url, string $postData): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result ?: '';
    }

    private function httpGet(string $url, string $token): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result ?: '';
    }

    private function registerUser(array $userData, string $provider): User
    {
        $name      = $userData['name'] ?? trim(($userData['first_name'] ?? 'User') . ' ' . ($userData['last_name'] ?? ''));
        $nameParts = explode(' ', trim($name), 2);
        $firstName = $nameParts[0] ?? 'User';
        $lastName  = $nameParts[1] ?? ucfirst($provider);
        $email     = $userData['email'] ?? null;

        if (!$email) {
            $email = strtolower($provider) . '_' . ($userData['id'] ?? uniqid()) . '@noreply.sociallogin';
        }

        $password = bin2hex(random_bytes(16));

        $user = app('auth')->register([
            'first_name'            => $firstName,
            'last_name'             => $lastName,
            'email'                 => $email,
            'password'              => $password,
            'password_confirmation' => $password,
        ], true);

        return $user;
    }

    private function linkAccount(User $user, string $provider, string $providerUserId, ?string $token): void
    {
        SocialAccount::create([
            'user_id'          => $user->id,
            'provider'         => $provider,
            'provider_user_id' => $providerUserId,
            'token'            => $token,
        ]);
    }

    private function updateToken(SocialAccount $account, ?string $token): void
    {
        if ($token) {
            $account->token = $token;
            $account->save();
        }
    }
}
