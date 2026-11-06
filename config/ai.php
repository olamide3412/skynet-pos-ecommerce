<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Active AI Provider
    |--------------------------------------------------------------------------
    |
    | Supported providers: gemini, openai, deepseek, claude
    |
    */
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'gemini'),

    /*
    |--------------------------------------------------------------------------
    | Supported AI Providers Configuration
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'gemini' => [
            'name'             => 'Google Gemini',
            'default_model'    => 'gemini-1.5-flash',
            'available_models' => [
                'gemini-1.5-flash' => 'Gemini 1.5 Flash (Fast & Recommended)',
                'gemini-2.0-flash' => 'Gemini 2.0 Flash (Next-Gen High Speed)',
                'gemini-1.5-pro'   => 'Gemini 1.5 Pro (Deep Reasoning)',
            ],
            'endpoint'         => 'https://generativelanguage.googleapis.com/v1beta/models',
        ],

        'openai' => [
            'name'             => 'OpenAI (ChatGPT)',
            'default_model'    => 'gpt-4o-mini',
            'available_models' => [
                'gpt-4o-mini'   => 'GPT-4o Mini (Fast & Cost Efficient)',
                'gpt-4o'        => 'GPT-4o (High Intelligence Flagship)',
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Legacy)',
            ],
            'endpoint'         => 'https://api.openai.com/v1/chat/completions',
        ],

        'deepseek' => [
            'name'             => 'DeepSeek',
            'default_model'    => 'deepseek-chat',
            'available_models' => [
                'deepseek-chat'     => 'DeepSeek-V3 Chat',
                'deepseek-reasoner' => 'DeepSeek-R1 Reasoner',
            ],
            'endpoint'         => 'https://api.deepseek.com/v1/chat/completions',
        ],

        'claude' => [
            'name'             => 'Anthropic Claude',
            'default_model'    => 'claude-3-5-haiku-20241022',
            'available_models' => [
                'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku (High Speed)',
                'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Latest Premium)',
            ],
            'endpoint'         => 'https://api.anthropic.com/v1/messages',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Generation Parameters
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'temperature' => 0.7,
        'max_tokens'  => 2048,
        'timeout'     => 30, // seconds
    ],
];

