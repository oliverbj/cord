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
        ],
    ],

    //Add more connections here if you need to connect to multiple CargoWise One eAdapters.

];
