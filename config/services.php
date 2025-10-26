<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // ConnectyCube WebRTC/chat configuration
    'connectycube' => [
        'app_id' => (int) env('CONNECTYCUBE_APP_ID', 0),
        // Algunos paneles de ConnectyCube nombran el secret como "API Key". Aceptamos ambos.
        'auth_key' => env('CONNECTYCUBE_AUTH_KEY', env('CONNECTYCUBE_API_KEY')),
        'auth_secret' => env('CONNECTYCUBE_AUTH_SECRET', env('CONNECTYCUBE_API_KEY')),
    // Si no se define un password explícito para los usuarios CC, usamos como fallback alguna credencial conocida
    // Esto permite crear/login de usuarios CC y conectar al chat incluso si no se configuró CONNECTYCUBE_DEFAULT_PASSWORD
    'default_password' => env('CONNECTYCUBE_DEFAULT_PASSWORD', env('CONNECTYCUBE_AUTH_KEY', env('CONNECTYCUBE_API_KEY', 'pg-default-pass'))),
        // Endpoints opcionales por región/cluster (por ejemplo EU)
        // CONNECTYCUBE_API_ENDPOINT=https://api-eu.connectycube.com
        // CONNECTYCUBE_CHAT_ENDPOINT=chat-eu.connectycube.com
        'api_endpoint' => env('CONNECTYCUBE_API_ENDPOINT'),
        'chat_endpoint' => env('CONNECTYCUBE_CHAT_ENDPOINT'),
    ],

];
