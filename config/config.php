<?php

// config for Oliverbj/Cord
return [

    /*
    |--------------------------------------------------------------------------
    | CargoWise One eAdapter - Credentials
    |--------------------------------------------------------------------------
    |
    | Here you may configure your username and password for the CargoWise One eAdapter
    | service.
    |
    */
    'base' => [
        'eadapter_connection' => [
            'url' => env('CORD_URL', ''),
            'username' => env('CORD_USERNAME', ''),
            'password' => env('CORD_PASSWORD', ''),
            'timeout' => env('CORD_TIMEOUT', 120),
            'connect_timeout' => env('CORD_CONNECT_TIMEOUT', 10),
        ],
    ],

    'NTG_TRN' => [
        'eadapter_connection' => [
            'url' => env('CORD_URL', ''),
            'username' => env('CORD_USERNAME', ''),
            'password' => env('CORD_PASSWORD', ''),
            'timeout' => env('CORD_TIMEOUT', 120),
            'connect_timeout' => env('CORD_CONNECT_TIMEOUT', 10),
        ],
    ],

    // Add more connections here if you need to connect to multiple CargoWise One eAdapters.

];
