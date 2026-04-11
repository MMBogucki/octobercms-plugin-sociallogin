<?php

return [
    'plugin' => [
        'name'        => 'Social Login',
        'description' => 'Logowanie i rejestracja przez Google i Facebook OAuth z integracją RainLab.User.',
    ],

    'settings' => [
        'label'       => 'Social Login',
        'description' => 'Konfiguracja Google i Facebook OAuth.',
    ],

    'components' => [
        'sociallogin' => [
            'name'        => 'Social Login',
            'description' => 'Wyświetla przyciski logowania przez Google i Facebook.',
        ],
        'socialregister' => [
            'name'        => 'Social Register',
            'description' => 'Wyświetla przyciski rejestracji przez Google i Facebook.',
        ],
        'socialauth' => [
            'name'        => 'Social Auth Handler',
            'description' => 'Wewnętrzny komponent obsługujący logowanie OAuth. Umieść na stronie /social-auth.',
        ],
    ],
];
