<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the AI providers below should be the
    | default for AI operations when no explicit provider is provided
    | for the operation. This should be any provider defined below.
    |
    */

    'default' => 'openai',
    'default_for_images' => 'gemini',
    'default_for_audio' => 'openai',
    'default_for_transcription' => 'openai',
    'default_for_embeddings' => 'openai',
    'default_for_reranking' => 'cohere',

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Below you may configure caching strategies for AI related operations
    | such as embedding generation. You are free to adjust these values
    | based on your application's available caching stores and needs.
    |
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Below are each of your AI providers defined for this application. Each
    | represents an AI provider and API key combination which can be used
    | to perform tasks like text, image, and audio creation via agents.
    |
    */

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        ],

        'azure' => [
            'driver' => 'azure',
            'key' => env('AZURE_OPENAI_API_KEY'),
            'url' => env('AZURE_OPENAI_URL'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2025-04-01-preview'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o'),
            'embedding_deployment' => env('AZURE_OPENAI_EMBEDDING_DEPLOYMENT', 'text-embedding-3-small'),
        ],

        'bedrock' => [
            'driver' => 'bedrock',
            'region' => env('AWS_BEDROCK_REGION', 'us-east-1'),
            'key' => env('AWS_BEARER_TOKEN_BEDROCK'),
            'access_key_id' => env('AWS_ACCESS_KEY_ID'),
            'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
            'session_token' => env('AWS_SESSION_TOKEN'),
            'use_default_credential_provider' => env('AWS_USE_DEFAULT_CREDENTIALS', true),
        ],

        'cohere' => [
            'driver' => 'cohere',
            'key' => env('COHERE_API_KEY'),
            'url' => env('COHERE_URL', 'https://api.cohere.com/v2'),
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key' => env('DEEPSEEK_API_KEY'),
            'url' => env('DEEPSEEK_URL', 'https://api.deepseek.com'),
        ],

        'eleven' => [
            'driver' => 'eleven',
            'key' => env('ELEVENLABS_API_KEY'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
            'models' => [
                'text' => [
                    'default' => env('GEMINI_DEFAULT_MODEL', 'gemini-3-flash-preview'),
                    'cheapest' => 'gemini-3.1-flash-lite-preview',
                    'smartest' => 'gemini-3.1-pro-preview',
                ],
            ],
            'chat_models' => [
                [
                    'value' => 'gemini-3-flash-preview',
                    'label' => 'Gemini 3 Flash',
                    'description' => 'Balanced speed and quality',
                ],
                [
                    'value' => 'gemini-3.1-pro-preview',
                    'label' => 'Gemini 3.1 Pro',
                    'description' => 'Best for deeper reasoning',
                ],
                [
                    'value' => 'gemini-3.1-flash-lite-preview',
                    'label' => 'Gemini 3.1 Flash-Lite',
                    'description' => 'Fastest and most cost-efficient',
                ],
                [
                    'value' => 'gemini-2.5-flash',
                    'label' => 'Gemini 2.5 Flash',
                    'description' => 'Stable general-purpose model',
                ],
                [
                    'value' => 'gemini-2.5-pro',
                    'label' => 'Gemini 2.5 Pro',
                    'description' => 'Stable high-intelligence model',
                ],
                [
                    'value' => 'gemini-2.5-flash-lite',
                    'label' => 'Gemini 2.5 Flash-Lite',
                    'description' => 'Stable low-cost model',
                ],
            ],
            'monthly_budget_usd' => env('GEMINI_MONTHLY_BUDGET_USD'),
            'chat_pricing_per_1m_tokens' => [
                'gemini-3-flash-preview' => ['input' => 0.50, 'output' => 3.00],
                'gemini-3.1-pro-preview' => ['input' => 2.00, 'output' => 12.00],
                'gemini-3.1-flash-lite-preview' => ['input' => 0.25, 'output' => 1.50],
                'gemini-2.5-flash' => ['input' => 0.30, 'output' => 2.50],
                'gemini-2.5-pro' => ['input' => 1.25, 'output' => 10.00],
                'gemini-2.5-flash-lite' => ['input' => 0.10, 'output' => 0.40],
            ],
            'video_models' => [
                [
                    'value' => 'veo-3.1-generate-preview',
                    'label' => 'Veo 3.1 Preview',
                    'description' => 'Highest quality with audio',
                ],
                [
                    'value' => 'veo-3.1-fast-generate-preview',
                    'label' => 'Veo 3.1 Fast Preview',
                    'description' => 'Faster generation with audio',
                ],
                [
                    'value' => 'veo-3.1-lite-generate-preview',
                    'label' => 'Veo 3.1 Lite Preview',
                    'description' => 'Lower-cost video with audio',
                ],
                [
                    'value' => 'veo-3.0-generate-001',
                    'label' => 'Veo 3',
                    'description' => 'Stable high-quality video with audio',
                ],
                [
                    'value' => 'veo-3.0-fast-generate-001',
                    'label' => 'Veo 3 Fast',
                    'description' => 'Stable fast video with audio',
                ],
                [
                    'value' => 'veo-2.0-generate-001',
                    'label' => 'Veo 2',
                    'description' => 'Stable video generation',
                ],
            ],
        ],

        'groq' => [
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY'),
            'url' => env('GROQ_URL', 'https://api.groq.com/openai/v1'),
        ],

        'jina' => [
            'driver' => 'jina',
            'key' => env('JINA_API_KEY'),
            'url' => env('JINA_URL', 'https://api.jina.ai/v1'),
        ],

        'mistral' => [
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),
            'url' => env('MISTRAL_URL', 'https://api.mistral.ai/v1'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        ],

        'openrouter' => [
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
            'url' => env('OPENROUTER_URL', 'https://openrouter.ai/api/v1'),
        ],

        'voyageai' => [
            'driver' => 'voyageai',
            'key' => env('VOYAGEAI_API_KEY'),
            'url' => env('VOYAGEAI_URL', 'https://api.voyageai.com/v1'),
        ],

        'xai' => [
            'driver' => 'xai',
            'key' => env('XAI_API_KEY'),
            'url' => env('XAI_URL', 'https://api.x.ai/v1'),
        ],
    ],

];
