<?php

return [
    'oracle' => [
        'driver'   => 'oracle',
        'tns'      => env('DB_TNS', ''),
        'host'     => env('DB_HOST', ''),
        'port'     => env('DB_PORT', '1521'),
        'database' => env('DB_DATABASE', ''),
        'username' => env('DB_USERNAME', ''),
        'password' => env('DB_PASSWORD', ''),
        'charset'  => 'AL32UTF8',
        'prefix'   => '',
    ],
];
