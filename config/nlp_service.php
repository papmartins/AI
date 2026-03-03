<?php

return [
    // NLP Microservice Configuration
    'nlp_service' => [
        'url' => env('NLP_SERVICE_URL', 'http://nlp_microservice:8001'),
        'timeout' => env('NLP_SERVICE_TIMEOUT', 10),
        'cache_expiry' => env('NLP_CACHE_EXPIRY', 3600),
    ],

    // API Endpoints
    'endpoints' => [
        'classify_intent' => '/classify-intent',
        'classify_multiple_intents' => '/classify-multiple-intents',
        'extract_entities' => '/extract-entities',
        'semantic_search' => '/semantic-search',
        'detect_language' => '/detect-language',
        'health' => '/health',
    ],

    // Fallback behavior
    'fallback' => [
        'enabled' => env('NLP_FALLBACK_ENABLED', true),
        'cache_fallback_results' => env('NLP_CACHE_FALLBACK', false),
    ]
];
