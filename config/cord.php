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

    'eadapter_connection' => [
        'url' => env('CW1_EADAPTER_URL', ''),
        'username' => env('CW1_EADAPTER_USERNAME', ''),
        'password' => env('CW1_EADAPTER_PASSWORD', ''),
    ],

];
