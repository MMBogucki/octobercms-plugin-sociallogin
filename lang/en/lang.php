<?php

return [
    'plugin' => [
        'name'        => 'Social Login',
        'description' => 'Google and Facebook OAuth login and registration with RainLab.User integration.',
    ],

    'settings' => [
        'label'       => 'Social Login',
        'description' => 'Configure Google and Facebook OAuth.',
    ],

    'components' => [
        'sociallogin' => [
            'name'        => 'Social Login',
            'description' => 'Displays Google and Facebook login buttons.',
        ],
        'socialregister' => [
            'name'        => 'Social Register',
            'description' => 'Displays Google and Facebook registration buttons.',
        ],
        'socialauth' => [
            'name'        => 'Social Auth Handler',
            'description' => 'Internal component that handles OAuth session login. Place on the /social-auth page.',
        ],
    ],
];
