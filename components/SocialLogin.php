<?php

namespace BadCookies\SocialLogin\Components;

use Cms\Classes\ComponentBase;
use BadCookies\SocialLogin\Models\Settings;

class SocialLogin extends ComponentBase
{
    public function componentDetails(): array
    {
        return [
            'name'        => 'Social Login',
            'description' => 'Displays Google and Facebook login buttons.',
        ];
    }

    public function defineProperties(): array
    {
        return [
            'linkMode' => [
                'title'       => 'Account linking mode',
                'description' => 'If enabled, the component is used to link social accounts to an existing logged-in user.',
                'type'        => 'checkbox',
                'default'     => false,
            ],
        ];
    }

    public function onRun(): void
    {
        $this->addCss('/plugins/badcookies/sociallogin/assets/css/sociallogin.css');

        $this->page['googleEnabled']   = Settings::isGoogleEnabled();
        $this->page['facebookEnabled'] = Settings::isFacebookEnabled();
        $this->page['isLoggedIn']      = (bool) app('auth')->getUser();
        $this->page['linkMode']        = (bool) $this->property('linkMode');
    }

    public function onLinkAccount(): void
    {
        session(['badcookies_oauth_intent' => 'link']);
    }
}
