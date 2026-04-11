<?php

use Illuminate\Support\Facades\Route;
use BadCookies\SocialLogin\Controllers\OAuthController;

// OAuth redirect – starts the login flow
Route::get('redirect/{provider}', [OAuthController::class, 'redirect'])
    ->name('badcookies.sociallogin.redirect')
    ->where('provider', 'google|facebook');

// OAuth callback – provider returns user here
Route::get('callback/{provider}', [OAuthController::class, 'callback'])
    ->name('badcookies.sociallogin.callback')
    ->where('provider', 'google|facebook');
