<?php

return [

    // ...existing services
    'minimax' => [
        'key' => env('MINIMAX_API_KEY'),
        'base_url' => env('MINIMAX_BASE_URL', 'https://api.minimax.io/v1'),
        'model' => env('MINIMAX_MODEL', 'MiniMax-M2.7'),
    ],

];
