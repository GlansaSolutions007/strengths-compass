<?php

return [

    'paths' => [
        'api/*',
        'strengthscompass/api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://strengthcompass.glansa.in',
        'http://localhost:5173',
        'http://localhost:5174',
        'https://assessments.axiscompass.co/'
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];



// return [

//         'paths' => ['api/*',
//          'strengthscompass/api/*',
//         'sanctum/csrf-cookie'
//         ],
//     'allowed_methods' => ['*'],

//     'allowed_origins' => [
//         'https://strengthcompass.glansa.in', // your frontend domain
//         'http://localhost:5173',
//          'http://localhost:5174',
//     ],

//     'allowed_origins_patterns' => [],

//     'allowed_headers' => ['*'],

//     'exposed_headers' => [],

//     'max_age' => 0,

//     'supports_credentials' => true,

// ];

