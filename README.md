# BadCookies.SocialLogin

An OctoberCMS V3 plugin that enables **Google** and **Facebook** OAuth login and registration with full **RainLab.User** integration.

---

## Features

- Login via Google and Facebook
- Registration via Google and Facebook
- **Automatic avatar download** from Google/Facebook on first registration
- Account linking – connect an OAuth provider to an existing RainLab.User account
- Backend Settings panel with separate configuration for each provider
- Three CMS components: `socialLogin`, `socialRegister`, `socialAuth`
- CSRF protection via stateless HMAC-signed state token
- Styled buttons using official Google and Facebook brand colors
- No external OAuth libraries required – uses native cURL

---

## Requirements

- OctoberCMS V3
- PHP 8.0+
- Plugin **RainLab.User** (^3.0)
- PHP **cURL** extension

---

## Installation

### 1. Copy the plugin

Place the `sociallogin` directory into:

```
plugins/badcookies/sociallogin/
```

### 2. Run the migration

```bash
php artisan october:migrate
```

### 3. Create the Auth Handler page in CMS ⚠️

**This step is required.** The plugin needs a dedicated CMS page to handle the actual session-based login. This is necessary because OAuth callback routes run through the Laravel Router, which operates in a separate context without access to the OctoberCMS session. Login therefore happens in two stages.

Create a new CMS page with the following configuration:

```ini
title = "Social Auth"
url = "/social-auth"
layout = "default"

[socialAuth]
==
{# This page renders nothing – it only handles the redirect #}
```

> You can change the page URL in **Settings → Social Login → General → Auth Handler Page URL**. Make sure the URL in settings matches the URL of the CMS page.

### 4. Configure OAuth providers

#### Google

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a project and enable the **Google+ API** / **Google People API**
3. Under **Credentials**, create an **OAuth 2.0 Client ID** (type: Web Application)
4. Add the redirect URI:
   ```
   https://your-domain.com/callback/google
   ```
5. Copy the **Client ID** and **Client Secret**

#### Facebook

1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Create an app (type: Consumer or Business)
3. Add the **Facebook Login** product
4. Under Login settings, add the redirect URI:
   ```
   https://your-domain.com/callback/facebook
   ```
5. Copy the **App ID** and **App Secret**

### 5. Enter credentials in the OctoberCMS backend

Go to **Settings → Social Login**

#### General tab
| Field | Description | Default |
|-------|-------------|---------|
| Redirect URL after login | Where to send the user after a successful OAuth login | `/` |
| Auth Handler Page URL | URL of the CMS page with the `[socialAuth]` component | `/social-auth` |

#### Google OAuth tab
| Field | Description |
|-------|-------------|
| Enable Google login | Toggle on/off |
| Client ID | From Google Cloud Console |
| Client Secret | From Google Cloud Console |

#### Facebook OAuth tab
| Field | Description |
|-------|-------------|
| Enable Facebook login | Toggle on/off |
| App ID | From Facebook Developers |
| App Secret | From Facebook Developers |

---

## Using CMS components

### Social Login

Displays login buttons. Place on your login page:

```ini
[socialLogin]
==
{% component 'socialLogin' %}
```

Account linking mode (for already logged-in users):

```ini
[socialLogin]
linkMode = 1
==
{% component 'socialLogin' %}
```

### Social Register

Displays registration buttons. Place on your registration page:

```ini
[socialRegister]
==
{% component 'socialRegister' %}
```

### Social Auth Handler (required)

Place on the dedicated auth handler page (see installation step 3):

```ini
[socialAuth]
==
```

---

## Avatar download

The plugin **automatically downloads and saves the user's profile picture** from Google or Facebook during the first registration or first OAuth login.

- The avatar is only downloaded if the user **does not already have one** – it will not overwrite a manually set avatar
- The image is saved as a standard RainLab.User attachment (`System\Models\File`) and accessible via `{{ user.avatar }}`
- A failed avatar download **does not interrupt** the login process – it is logged as a warning only
- Google provides the avatar via the `picture` field; Facebook via `picture.data.url` (400px size)

Displaying the avatar in a Twig template:

```twig
{% if user.avatar %}
    <img src="{{ user.avatar.getThumb(100, 100, {'mode': 'crop'}) }}" alt="Avatar">
{% endif %}
```

---

## Displaying flash messages

```twig
{% if this.session.badcookies_social_success %}
    <div class="cri-social-success">{{ this.session.badcookies_social_success }}</div>
{% endif %}
```

---

## How login works (architecture)

Due to OctoberCMS architecture, the login process is split into two stages:

```
1. User clicks Google / Facebook button
        ↓
2. /redirect/google  (Laravel Router)
   – builds the provider authorization URL and redirects
        ↓
3. Provider callback
   /callback/google  (Laravel Router)
   – validates state (HMAC), exchanges code for token
   – fetches user data, registers or finds existing account
   – downloads avatar if not already set
   – stores user_id in a short-lived encrypted cookie (5 min)
        ↓
4. /social-auth  (CMS Router – session works correctly here)
   – [socialAuth] component reads the cookie
   – logs in the user via app('auth')->login()
   – CMS session saved correctly
        ↓
5. Redirect to configured URL (default: /)
```

---

## File structure

```
plugins/badcookies/sociallogin/
├── Plugin.php
├── routes.php
├── composer.json
├── README.md
├── assets/
│   └── css/
│       └── sociallogin.css
├── components/
│   ├── SocialLogin.php
│   ├── SocialRegister.php
│   ├── SocialAuth.php                  ← session login handler
│   ├── sociallogin/
│   │   └── default.htm
│   ├── socialregister/
│   │   └── default.htm
│   └── socialauth/
│       └── default.htm
├── controllers/
│   └── OAuthController.php
├── lang/
│   └── pl/
│       └── lang.php
├── models/
│   ├── Settings.php
│   ├── SocialAccount.php
│   └── settings/
│       └── fields.yaml
├── services/
│   └── OAuthService.php
└── updates/
    ├── version.yaml
    └── create_social_accounts_table.php
```

---

## Security

- The `state` parameter is verified using **HMAC-SHA256** signed with `APP_KEY` – stateless, no session required, CSRF-safe
- Data between the OAuth callback and the CMS auth page is passed via a **Laravel encrypted cookie** (`encrypt()`) valid for 5 minutes
- Passwords for newly registered users are randomly generated (`bin2hex(random_bytes(16))`) – users can reset via "Forgot password"
- Users without an email address (e.g. Facebook without email permission) receive a placeholder address `@noreply.sociallogin`

---

## License

MIT
