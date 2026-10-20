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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    'n8n' => [
        'webhook_url' => env('N8N_WEBHOOK_URL'),
    ],

    'groq' => [
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
        'api_keys' => [
            env('GROQ_API_KEY_1'),
            env('GROQ_API_KEY_2'),
            env('GROQ_API_KEY_3'),
            env('GROQ_API_KEY_4'),
            env('GROQ_API_KEY_5'),
        ],
    ],

    'government_plans' => [
        'sites' => [
            '280002542548' => [
                'url' => env('LULA_PROPOSALS_URL', 'https://lula.com.br/propostas/'),
                'allowed_hosts' => ['lula.com.br', 'www.lula.com.br'],
                'allowed_path_prefixes' => ['/propostas'],
                'max_pages' => 10,
            ],
            '280002551544' => [
                'url' => env('FLAVIO_GOVERNMENT_PLAN_URL', 'https://www.flaviobolsonaro.com.br/plano-de-governo'),
                'allowed_hosts' => ['flaviobolsonaro.com.br', 'www.flaviobolsonaro.com.br'],
                'allowed_path_prefixes' => ['/plano-de-governo'],
                'max_pages' => 20,
            ],
        ],
    ],

    'scheduler' => [
        'token' => env('SCHEDULER_TOKEN'),
    ],

];
