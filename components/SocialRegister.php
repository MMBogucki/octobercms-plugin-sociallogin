<?php

namespace BadCookies\SocialLogin\Components;

use Cms\Classes\ComponentBase;
use BadCookies\SocialLogin\Models\Settings;

class SocialRegister extends ComponentBase
{
    public function componentDetails(): array
    {
        return [
            'name'        => 'Social Register',
            'description' => 'Displays Google and Facebook registration buttons.',
        ];
    }

    public function defineProperties(): array
    {
        return [];
    }

    public function onRun(): void
    {
        $this->addCss('/plugins/badcookies/sociallogin/assets/css/sociallogin.css');

        $this->page['googleEnabled']   = Settings::isGoogleEnabled();
        $this->page['facebookEnabled'] = Settings::isFacebookEnabled();
    }
}
