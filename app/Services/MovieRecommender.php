<?php

namespace App\Services;

use App\Services\Recommendation\MovieRecommendationService;

/**
 * @deprecated Use MovieRecommendationService instead
 */
class MovieRecommender extends MovieRecommendationService
{
    // This class is kept for backward compatibility
    // All new development should use MovieRecommendationService directly
}