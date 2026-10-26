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

    // ---- AI question reader (app/Services/Imports/AiPdfParser.php + Ai/) ----
    // Set ONE key. AI_PROVIDER=auto uses the first that has a key: Gemini, Vertex AI, OpenAI, Claude.
    'ai' => [
        'provider' => env('AI_PROVIDER', 'auto'),     // auto | gemini | vertex | openai | anthropic
        'pages_per_request' => (int) env('AI_PAGES_PER_REQUEST', 4),
    ],
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),                // https://aistudio.google.com/apikey
        'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'), // gemini-3.5-flash-lite is cheaper
    ],
    'vertex' => [
        'key' => env('VERTEX_API_KEY'),                // Google Cloud console > Vertex AI > API keys (see docs/AI-READER.md)
        'model' => env('VERTEX_MODEL', 'gemini-3.8-flash'), // a model your project can call in Vertex AI (gemini-3.5-flash-lite is cheaper)
    ],
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5-mini'),  // gpt-5-nano is cheaper
    ],
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        // claude-opus-5-5 reads most accurately; claude-sonnet-5-5 is about half the price.
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
