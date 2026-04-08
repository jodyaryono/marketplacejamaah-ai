<?php

return [
    /*
     * |--------------------------------------------------------------------------
     * | Third Party Services
     * |--------------------------------------------------------------------------
     * |
     * | This file is for storing the credentials for third party services such
     * | as Mailgun, Postmark, AWS and more. This file provides the de facto
     * | location for this type of information, allowing packages to have
     * | a conventional file to locate the various service credentials.
     * |
     */
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'resend' => [
        'key' => env('RESEND_KEY'),
    ],
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'wa_gateway' => [
        'url' => env('WA_GATEWAY_URL', 'https://integrasi-wa.jodyaryono.id/api'),
        'token' => env('WA_GATEWAY_TOKEN', ''),
        'phone_id' => env('WA_GATEWAY_PHONE_ID', ''),
        'group_id' => env('WA_GATEWAY_GROUP_ID', ''),
        'admin_phone' => env('WA_ADMIN_PHONE', ''),
        'master_phone' => env('WA_MASTER_PHONE', ''),
        'webhook_secret' => env('WEBHOOK_SECRET', ''),
    ],
    'whatsapp' => [
        'group_link' => env('WHATSAPP_GROUP_LINK', ''),
    ],
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
        'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models'),
    ],
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        'vision_model' => env('GROQ_VISION_MODEL', 'meta-llama/llama-4-scout-17b-16e-instruct'),
        'endpoint' => env('GROQ_ENDPOINT', 'https://api.groq.com/openai/v1/chat/completions'),
    ],
];
