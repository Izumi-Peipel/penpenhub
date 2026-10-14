<?php

use Laravel\Fortify\Features;

// Hanya kunci yang berbeda dari default paket (digabung oleh mergeConfigFrom).
return [
    'home' => '/dashboard',

    'features' => [
        Features::resetPasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ],
];
