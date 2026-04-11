<?php

namespace BadCookies\SocialLogin;

use System\Classes\PluginBase;
use System\Classes\SettingsManager;
use RainLab\User\Models\User;
use BadCookies\SocialLogin\Models\SocialAccount;

class Plugin extends PluginBase
{
    public $require = ['RainLab.User'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Social Login',
            'description' => 'Google and Facebook OAuth login and registration with RainLab.User integration.',
            'author'      => 'BadCookies',
            'icon'        => 'icon-sign-in',
        ];
    }

    public function registerComponents(): array
    {
        return [
            \BadCookies\SocialLogin\Components\SocialLogin::class    => 'socialLogin',
            \BadCookies\SocialLogin\Components\SocialRegister::class => 'socialRegister',
            \BadCookies\SocialLogin\Components\SocialAuth::class     => 'socialAuth',
        ];
    }

    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label'       => 'Social Login',
                'description' => 'Configure Google and Facebook OAuth.',
                'category'    => SettingsManager::CATEGORY_USERS,
                'icon'        => 'icon-sign-in',
                'class'       => \BadCookies\SocialLogin\Models\Settings::class,
                'order'       => 510,
                'keywords'    => 'oauth google facebook social login',
                'permissions' => ['badcookies.sociallogin.settings'],
            ],
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'badcookies.sociallogin.settings' => [
                'label' => 'Manage Social Login settings',
                'tab'   => 'Social Login',
            ],
        ];
    }

    public function boot(): void
    {
        User::extend(function (User $model) {
            $model->hasMany['social_accounts'] = [
                SocialAccount::class,
                'key' => 'user_id',
            ];
        });
    }
}
