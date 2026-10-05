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

    'scheduler' => [
        'token' => env('SCHEDULER_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | TSE — DivulgaCandContas (API REST)
    |--------------------------------------------------------------------------
    |
    | Substitui os CSVs de Dados Abertos na importação de candidatos. Base e
    | endpoints ficam centralizados aqui para que nenhuma outra camada monte
    | URL na mão. Os timeouts são altos de propósito: o TSE responde devagar
    | e derrubar o sync por causa de um pico pontual seria pior do que esperar
    | um pouco mais.
    |
    */
    'tse' => [
        'divulgacandcontas' => [
            'base_url' => env('TSE_DIVULGACANDCONTAS_URL', 'https://divulgacandcontas.tse.jus.br/divulga/rest/v1'),

            // Fotos (fotoUrl) e documentos (idArquivo) NÃO passam pela base
            // /rest/v1 — ficam em /rest/arquivo, fora do versioning.
            'arquivo_url' => env('TSE_DIVULGACANDCONTAS_ARQUIVO_URL', 'https://divulgacandcontas.tse.jus.br/divulga/rest/arquivo'),

            'timeout' => (int) env('TSE_DIVULGACANDCONTAS_TIMEOUT', 30),

            // O TSE derruba requisição com 5xx com frequência; repetir é
            // barato e evita perder metade de uma UF inteira no meio do sync.
            'retries' => (int) env('TSE_DIVULGACANDCONTAS_RETRIES', 3),

            // Pausa entre tentativas, em milissegundos.
            'retry_delay' => (int) env('TSE_DIVULGACANDCONTAS_RETRY_DELAY', 1000),

            // O TSE não responde bem a software sem User-Agent de navegador.
            'user_agent' => env(
                'TSE_DIVULGACANDCONTAS_USER_AGENT',
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            ),
        ],
    ],

];
