# Movie Recommendation System - Rubix ML with KNN Regression

## Overview

This implementation provides personalized movie recommendations using a machine learning approach with Rubix ML:
- **Algorithm**: K-Nearest Neighbors (KNN) Regressor
- **Distance Metric**: Cosine similarity
- **Neighbors**: 10 weighted neighbors
- **Features**: 4 demographic and popularity features (rental_percentage, avg_rental_age, avg_rating, avg_good_rating_age)
- **Key Focus**: Age-based demographics and popularity patterns

## Architecture

```
User Interactions → Dataset Preparation (CSV) → KNN Model Training → ML Predictions → Fallback Handler → Frontend Display
```

## Components

### 1. MovieRecommender Service (`app/Services/MovieRecommender.php`)

The core recommendation engine that:
- **Prepares dataset** from user interactions (ratings, rentals, wishlists)
- **Extracts numeric features**:
  - **rental_percentage**: Percentage of users who rented the movie (0-1)
  - **avg_rental_age**: Average age of users who rented the movie
  - **avg_rating**: Average rating of the movie (1-5)
  - **avg_good_rating_age**: Average age of users who gave good ratings (>3.5)
- **Trains KNN Regressor** using 10 weighted neighbors with cosine distance
- **Makes predictions** for unwatched movies using ML model
- **Persists model** to filesystem for reuse
- **Falls back** to popularity-based recommendations if ML inference fails
- **Caches recommendations** for frequent users (≥5 interactions)
- **Automatic retraining** when feature dimensions change

### 2. Recommendation API (`app/Http/Controllers/RecommendationController.php`)

REST API endpoints:
- `GET /api/recommendations/personalized` - Personalized ML-based recommendations (auth required)
- `GET /api/recommendations/popular` - Popular movies (auth required)
- `GET /api/recommendations/popular-public` - Public popular movies
- `POST /api/recommendations/retrain` - Retrain the ML model (auth required)

### 3. Data Model

The system uses these data sources:
- **User Ratings** (1-5 stars) - for training labels and genre averages
- **Rental History** - indicates user engagement with movies
- **Wishlist Items** - tracks intended movie viewing
- **Movie Metadata** (year, genre) - numeric features for prediction
- **Genre Ratings** - averaged ratings per genre

### 4. Machine Learning Components

**Model Files:**
- `storage/app/movie_recommender.model` - Trained KNN model (serialized)
- `storage/app/movie_recommendations.csv` - Training dataset
- `storage/app/movie_metadata.json` - Movie metadata reference
- `storage/app/popularity_confidence.json` - Cached popularity confidence scores

## Features

### Machine Learning-Based Predictions
- Uses trained KNN Regressor to predict user ratings for unseen movies
- Predicts ratings on 1-5 scale based on historical user patterns
- Considers 10 nearest neighbors using cosine distance in feature space

### Feature Engineering

**Current Implementation (4 features):**
- **rental_percentage**: Percentage of users who rented the movie (0-1)
  - Calculated as: `movie_rentals_count / total_users`
  - Indicates popularity and engagement

- **avg_rental_age**: Average age of users who rented the movie
  - Calculated from users' birth dates
  - Helps find movies popular with specific age groups

- **avg_rating**: Average rating of the movie (1-5 scale)
  - Overall quality indicator
  - Direct measure of user satisfaction

- **avg_good_rating_age**: Average age of users who gave good ratings (>3.5)
  - Focuses on users who really liked the movie
  - Identifies age groups that appreciate the movie

**Key Characteristics:**
- All features are **numeric** (no binary indicators)
- Focus on **demographics** (age patterns) and **popularity**
- **User age** used for filtering (not as feature)
- **movie_id** stored in CSV but not used as feature

### Collaborative Filtering via KNN
- K-Nearest Neighbors with k=10 neighbors
- Weighted neighbors (closer neighbors have more influence)
- Cosine distance metric for measuring feature similarity
- Finds users with similar rating patterns and movie preferences

### Confidence Scoring
- Each recommendation includes a confidence score (0-1)
- **Two confidence calculation methods:**
  - **ML Predictions**: Based on number of ratings (more ratings = higher confidence)
  - **Popular Recommendations**: Multi-factor confidence including ratings, rentals, and quality
- **Popularity Confidence Formula**:
  - Ratings count: 0-0.5 (100 ratings = full)
  - Rentals count: 0-0.3 (200 rentals = full)
  - Quality (rating > 3): 0-0.2
  - Minimum confidence: 0.6 for popular items
- **Caching**: Popularity confidence scores cached during training for performance
- Full confidence (1.0) at 50 ratings per movie for ML predictions

### Smart Caching
- Caches recommendations for frequent users (≥5 interactions)
- Cache duration: 30 minutes for ML-based recommendations, 15 minutes for fallback
- Automatic cache invalidation when user interacts with movies

### Fallback Strategy
- If ML prediction fails for any reason, falls back to popularity-based recommendations
- Recommends highly-rated movies from genres user has rated
- Uses quality (50%) + popularity (30%) + demographics (20%) for fallback
- Shorter cache duration (15 min) for fallback recommendations

## Usage

### Getting Recommendations via API

**Personalized (authenticated):**
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     http://your-app.test/api/recommendations/personalized?limit=6
```

**Popular movies:**
```bash
curl http://your-app.test/api/recommendations/popular-public?limit=6
```

### Integration in Frontend

The system is already integrated into:
- `MovieController::index()` - Shows personalized suggestions
- Dashboard - Recommended movies section  
- Movie detail pages - "You might also like" suggestions

## Algorithm Details

### Dataset Preparation

**CSV Structure:**
```
movie_id, rental_percentage, avg_rental_age, avg_rating, avg_good_rating_age
94, 0.1765, 36.2222, 3.5, 41.9091, 3
21, 0.1176, 24.5, 3.6154, 24.75, 4
...
```

**Columns:**
1. `movie_id`: Movie identifier (for reference, not used as feature)
2. `rental_percentage`: Percentage of users who rented (0-1)
3. `avg_rental_age`: Average age of renters
4. `avg_rating`: Average movie rating (1-5)
5. `avg_good_rating_age`: Average age of users who gave good ratings

**Feature Count:** 4 features (columns 2-5), 1 target (column 6)

The system prepares training data from:
1. All user ratings as target variable (1-5 scale)
2. Rental history for popularity calculation
3. User ages for demographic patterns
4. Rating distributions for quality assessment

### KNN Model Configuration

```
Algorithm: K-Nearest Neighbors Regressor
Distance Metric: Cosine Similarity
Number of Neighbors (k): 10
Weighting: Weighted by distance (closer neighbors have more influence)
```

### Prediction Process

For each unwatched movie:
1. Extract numeric features based on movie and user data
2. Find 10 most similar movies in feature space using cosine distance
3. Use weighted average of similar movies' ratings as prediction
4. Clamp prediction to valid range (1.0 - 5.0)

### Example Feature Vector
```
Movie: "Inception" (2010, Sci-Fi)
User: Jane (age 28, rated 20 movies)

Features for prediction:
[
  0.25,              // rental_percentage (25% of users rented this)
  32.5,              // avg_rental_age (average renter age: 32.5)
  4.2,               // avg_rating (average rating: 4.2)
  35.0               // avg_good_rating_age (avg age of good raters: 35.0)
]

Features in training data:
[
  94,                // movie_id (for reference)
  0.25,              // rental_percentage
  32.5,              // avg_rental_age
  4.2,               // avg_rating
  35.0,              // avg_good_rating_age
  4                  // rating (target variable)
]

Output: predicted_rating = 4.3
```

### Popularity Confidence Example
```
Movie: "The Shawshank Redemption" (1994, Drama)
- Ratings: 125 ratings (avg: 4.8)
- Rentals: 87 rentals
- Quality: 4.8 (high rating)

Confidence Calculation:
- Rating confidence: min(0.5, 125/100) = 0.5
- Rental confidence: min(0.3, 87/200) = 0.3
- Quality confidence: min(0.2, (4.8-3)/2) = 0.2
- Total confidence: max(0.6, 0.5 + 0.3 + 0.2) = 1.0

Output: confidence = 1.0 (maximum confidence)
```

### Model Persistence

- Model saved to disk after training: `storage/app/movie_recommender.model`
- Reused across requests to avoid retraining
- Retrained on demand via API endpoint
- Automatic reload on each request if model exists

## Performance Considerations

### Scalability
- Efficient CSV-based dataset handling via Rubix ML Extractors
- Caching reduces model inference load for frequent users
- KNN operates in feature space, not requiring explicit training per prediction
- Model persistence avoids repeated training

### Cold Start Problem  
- **New users**: Uses fallback algorithm with genre preferences
- **New movies**: Recommended based on initial ratings and genre
- Minimum 5 ratings per user to benefit from personalized ML predictions

### Feature Space Efficiency
- Only numeric features → no categorical encoding overhead
- Cosine distance computation optimized for dense vectors
- 10 neighbors provides good accuracy-speed tradeoff

### Data Requirements

**Minimum Requirements:**
- At least 5 users with birth_date filled
- At least 20 movies in database
- At least 10 ratings total for model training
- All users must have birth_date for age calculations

**Recommended for Good Performance:**
- 50+ users with diverse age ranges
- 100+ movies across multiple genres
- 100+ ratings for meaningful patterns
- Balanced age distribution for demographic features

**Critical Data Fields:**
- `users.birth_date`: Required for age calculations
- `movies.age_rating`: Required for age-appropriate filtering
- `ratings.rating`: Required for target variable
- `rentals.user_id/movie_id`: Required for rental percentage

**Data Quality Checks:**
```bash
# Check users with birth_date
php artisan tinker
>> App\Models\User::whereNotNull('birth_date')->count();

# Check movies with age_rating
php artisan tinker
>> App\Models\Movie::whereNotNull('age_rating')->count();

# Check total ratings
php artisan tinker
>> App\Models\Rating::count();
```

### Memory Usage
- Model stored on filesystem (not in memory by default)
- Loaded on-demand for inference
- CSV dataset kept only during training
- Metadata kept in JSON for reference
- Popularity confidence cache stored as JSON for fast access

### Popularity Confidence Caching
- **Automatic caching**: Confidence scores calculated and cached during model training
- **Fast retrieval**: Popular recommendations use pre-calculated confidence scores
- **Cache structure**: JSON file with movie IDs sorted by confidence
- **Fallback**: Real-time calculation if cache not available
- **Benefits**:
  - Eliminates repeated confidence calculations
  - Consistent confidence scores across requests
  - Faster response times for popular recommendations
  - Reduced database load

## Monitoring and Maintenance

### Model Training and Retraining
- Initial training happens automatically on first recommendation request if model doesn't exist
- Can be manually triggered via `POST /api/recommendations/retrain` endpoint
- Training includes:
  1. Dataset preparation from user ratings and rentals
  2. CSV export with 4 features + target to `storage/app/movie_recommendations.csv`
  3. KNN model training on 4 numeric features (rental_percentage, avg_rental_age, avg_rating, avg_good_rating_age)
  4. Model serialization to disk with feature verification
  5. **Popularity confidence cache generation** for fallback recommendations
- Training time: Typically <1 minute for medium datasets
- **Retrain response includes**:
  - Model training time in seconds
  - Model save status (success/failure)
  - Confidence cache creation status
  - Cache file path and feature count verification

### Model Performance Metrics
- Track prediction accuracy via user engagement with recommendations
- Monitor recommendation diversity and coverage across age groups
- Measure fallback frequency (high fallback may indicate data quality issues)
- Compare ML predictions vs actual user ratings (post-hoc validation)
- **Feature stability**: Verify 4-feature consistency across training/prediction
- **Cache hit rate**: Monitor how often cached recommendations are used

### Cache Management
- Automatic expiration: 30 min for ML recommendations, 15 min for fallback
- Manual invalidation available: `MovieRecommender::clearUserCache(User)`
- Clear all caches: `php artisan cache:clear` or flush via `clearAllCaches()`

### Common Issues and Solutions

| Issue | Cause | Solution |
|-------|-------|----------|
| Model file missing | First run or deleted | Automatic retraining on next request |
| Low predictions | Insufficient training data | Collect more ratings or retrain |
| All similar scores | Feature space overlap | Check feature engineering or retrain |
| Fallback triggered frequently | ML prediction errors | Check data integrity, retrain |
| Cached stale results | Cache TTL not expired | Manual cache clear or wait |
| Low confidence scores | Insufficient ratings or rental data | Collect more data or adjust confidence thresholds |
| Popular recommendations inconsistent | Confidence cache outdated or missing | Retrain model to regenerate cache |
| Age filtering too strict | Excluding too many movies | Review age_rating values and user age data |
| Feature calculation errors | Missing birth_date or rental data | Ensure all users have birth_date filled |


## Advantages of Rubix ML Approach

### 1. Collaborative Filtering
- Leverages patterns from similar users automatically
- K-NN finds users with matching taste in feature space
- More sophisticated than rule-based weighting

### 2. Adaptive Learning
- Model adapts to new user behaviors and ratings
- Captures complex interactions between features
- Improves with more data over time

### 3. Personalization
- **Demographic-based features**: Focus on age patterns of users who rented and rated movies
- **Popularity-based features**: Combines rental percentage with rating quality
- **Age-appropriate filtering**: Excludes movies with age_rating > user's age
- **User age**: Used for filtering only, not as a prediction feature

### 4. Scalability
- Pure numerical computation (no string/categorical overhead)
- Cosine distance efficiently computable on 4-dimensional vectors
- Predictions made without exponential complexity growth
- Fixed 4-feature architecture ensures consistent performance

### 5. Model Persistence
- Trained model reused across requests
- No need to retrain on every prediction
- Significant performance improvement vs re-computation
- **Automatic retraining** when features change

### 6. Fallback Robustness
- Graceful degradation to popularity-based approach
- Hybrid strategy ensures recommendations always available
- Prevents recommendation service outage
- **Improved demographic fallback** with age filtering

### 7. Confidence-Based Recommendations
- **Dynamic confidence scoring** for popular recommendations
- **Multi-factor confidence** considering ratings, rentals, and quality
- **Cached confidence scores** for performance optimization
- **Consistent ordering** across multiple requests
- **Minimum confidence threshold** ensures quality recommendations

## Dependencies

- **Rubix ML** - Machine learning library for PHP
- **Laravel** - Web framework for API and caching
- **Storage Driver** - For persisting model to filesystem

## Setup and Usage

### Initial Setup
```bash
# Install dependencies
composer install

# Create storage directories if needed
mkdir -p storage/app

# First recommendation request will auto-train model
curl http://your-app.test/api/recommendations/personalized
```

### Retrain Model
```bash
# Via API (requires authentication)
curl -X POST -H "Authorization: Bearer TOKEN" \
     http://your-app.test/api/recommendations/retrain

# Via CLI
php artisan tinker
>> app(App\Services\MovieRecommender::class)->retrain()
```

### Clear Cache
```bash
# Clear all recommendations cache
php artisan cache:clear

# Clear specific user cache (in code)
app(App\Services\MovieRecommender::class)->clearUserCache($user)
```
- Faster recommendations
- Lower resource usage

### 5. Maintainability
- Easier to debug and improve
- Simple to adjust weights and parameters
- No dependency on ML libraries

## Current Implementation Status ✅

### Completed Features
1. **4-Feature Architecture**: rental_percentage, avg_rental_age, avg_rating, avg_good_rating_age
2. **Age-Based Filtering**: Excludes movies with age_rating > user's age
3. **Demographic Focus**: All features based on age patterns and popularity
4. **Automatic Cache Clearing**: Caches invalidated on model retraining
5. **Feature Verification**: Automatic detection of feature count changes

### Future Enhancements

1. **Content-Based Filtering**: Add movie content analysis (genre, director, actors)
2. **Temporal Patterns**: Consider time-based preferences (weekend vs weekday)
3. **Social Features**: Incorporate friend recommendations
4. **Mood Detection**: Detect user mood from interaction patterns
5. **A/B Testing**: Test different feature weight combinations
6. **Confidence Learning**: Machine learning for confidence prediction
7. **Real-time Confidence Updates**: Incremental confidence cache updates
8. **Confidence-Based Blending**: Mix ML and popularity based on confidence
9. **Feature Importance Analysis**: Understand which features contribute most
10. **Hybrid Recommendations**: Combine demographic and content-based features

## Troubleshooting

### Common Issues

**No recommendations shown:**
- Check if user has interacted with all available movies
- Verify user has birth_date filled for demographic analysis
- Ensure movies have sufficient rating data

**Low-quality recommendations:**
- Adjust scoring weights (quality vs popularity vs demographics)
- Increase minimum rating threshold
- Add more demographic factors (location, language)
- Check if confidence cache needs regeneration

**Slow recommendations:**
- Check database indexing on ratings and rentals tables
- Verify caching is working for frequent users
- Consider pre-computing some scores
- Ensure confidence cache exists and is being used

**Confidence cache issues:**
- Cache file missing or corrupted
- Outdated confidence scores
- Solution: Retrain model to regenerate cache

## Dependencies

- Laravel framework
- Standard PHP extensions
- Database with proper indexing

## License

This recommendation system is part of the main application and inherits its license (MIT).