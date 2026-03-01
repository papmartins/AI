<?php

return [
    /**
     * Global Model Path Configuration
     */
    'model_path' => env('ML_MODEL_PATH', 'app/ml.model'),
    
    /**
     * NLP Model Configuration
     */
    'nlp' => [
        'model_path' => env('NLP_MODEL_PATH', 'app/nlp_intention_classifier.model'),
        'supported_languages' => ['pt', 'en', 'es'],
        /**
         * Compound Intent Combinations
         */
        'compound_intents' => [
            ['actor', 'director'],
            ['actor', 'genre'],
            ['actor', 'year'],
            ['director', 'genre'],
            ['director', 'year'],
            ['genre', 'year'],
            ['genre', 'rating']
        ],
    
    ],
    
    /**
     * Iris Classifier Configuration
     */
    'iris' => [
        'model_path' => env('IRIS_MODEL_PATH', 'app/iris.model'),
        'dataset_path' => env('IRIS_DATASET_PATH', 'app/iris.csv'),
    ],
    
    /**
     * Movie Recommender Configuration
     */
    'movie_recommender' => [
        'model_path' => env('MOVIE_RECOMMENDER_MODEL_PATH', 'app/movie_recommender.model'),
        'dataset_path' => env('MOVIE_RECOMMENDER_DATASET_PATH', 'app/movie_recommendations.csv'),
        'metadata_path' => env('MOVIE_RECOMMENDER_METADATA_PATH', 'app/movie_metadata.json'),
        'confidence_cache_path' => env('MOVIE_RECOMMENDER_CONFIDENCE_CACHE_PATH', 'app/popularity_confidence.json'),
    ],
    
    /**
     * Anomaly Detector Configuration
     */
    'anomaly_detector' => [
        'model_path' => env('ANOMALY_DETECTOR_MODEL_PATH', 'app/anomaly_detector.model'),
        'dataset_path' => env('ANOMALY_DETECTOR_DATASET_PATH', 'app/anomaly_dataset.csv'),
    ],
    
    /**
     * Training Configuration
     */
    'training' => [
        'sample_size' => 1000,
        'test_split' => 0.2,
        'random_state' => 42,
    ],
    /**
     * Intent Classification
     */
    'intents' => [
        'actor',
        'director', 
        'genre',
        'year',
        'rating',
        'title',
        'recommendation'
    ],
    
    /**
     * Response Configuration
     */
    'responses' => [
        'max_results' => 10,
        'default_language' => 'en',
        'fallback_language' => 'en'
    ],
    
    /**
     * Cache Configuration
     */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour in seconds
    ],
        
];