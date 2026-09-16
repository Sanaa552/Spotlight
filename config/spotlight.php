<?php

return [
    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super Administrateur'),
        'email' => env('SUPER_ADMIN_EMAIL', 'admin@spotlight.cm'),
        'telephone' => env('SUPER_ADMIN_TELEPHONE'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    'uploads' => [
        'max_files' => 5,
        'max_file_kilobytes' => 10240,
        'max_total_kilobytes' => 61440,
    ],
];
