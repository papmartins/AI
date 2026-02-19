# Movie Recommendation System - Quality, Popularity & Demographics

## Overview

This implementation provides personalized movie recommendations based on a hybrid approach that combines:
- **Movie Quality** (average ratings)
- **Movie Popularity** (rental count)
- **User Demographics** (age and gender similarity)

## Architecture

```
User Data → Quality/Popularity/Demographics Analysis → Scored Recommendations → Frontend Display
```

## Components

### 1. MovieRecommender Service (`app/Services/MovieRecommender.php`)

The core recommendation engine that:
- Calculates quality scores based on average movie ratings
- Measures popularity based on rental frequency
- Analyzes demographic similarity between users
- Combines scores with weighted approach (40% quality, 30% popularity, 30% demographics)
- Provides personalized recommendations with confidence scores
- Falls back to simplified approach if main algorithm fails

### 2. Recommendation API (`app/Http/Controllers/RecommendationController.php`)

REST API endpoints:
- `GET /api/recommendations/personalized` - Personalized recommendations (auth required)
- `GET /api/recommendations/popular` - Popular movies (auth required)
- `GET /api/recommendations/popular-public` - Public popular movies
- `POST /api/recommendations/retrain` - Retrain the model (auth required)

### 3. Data Model

The system uses these data sources:
- **User Ratings** (1-5 stars) - for quality calculation
- **Rental History** (movies user has rented) - for popularity calculation
- **Wishlist Items** (movies user wants to watch) - for exclusion
- **Movie Metadata** (genre, year, etc.) - for context
- **User Demographics** (age, gender, birth_date) - for similarity analysis

### 4. User Model Enhancements

The `User` model now includes:
- `birth_date` field (date) - stored in database
- `gender` field (string) - stored in database  
- `age` accessor - calculated from birth_date

## Features

### Quality-Based Recommendations
- Uses average movie ratings as primary quality indicator
- Higher-rated movies get higher priority
- Considers only movies with sufficient rating data

### Popularity-Based Recommendations
- Uses rental count as popularity metric
- More frequently rented movies get higher priority
- Normalizes popularity scores for fair comparison

### Demographics-Based Recommendations
- Analyzes users who rated movies highly (4-5 stars)
- Calculates age similarity between current user and similar users
- Calculates gender similarity between current user and similar users
- Combines both metrics for comprehensive demographic matching

### Confidence Scoring
- Each recommendation includes a confidence score (0-1)
- Calculated from weighted combination of quality, popularity, and demographics
- Higher confidence = more likely the user will enjoy the movie

### Smart Caching
- Caches recommendations for frequent users (≥5 interactions)
- Cache duration: 30 minutes for main recommendations, 15 minutes for fallback
- Automatic cache invalidation when user interacts with movies

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

### Scoring System

Each movie receives a combined score calculated as:

```
combined_score = (quality_score × 0.4) + (popularity_score × 0.3) + (demographics_score × 0.3)
```

#### Quality Score
- Based on `ratings_avg_rating` (0-5 scale)
- Movies with higher average ratings score better
- Weight: 40% of final score

#### Popularity Score
- Based on `rentals_count` (normalized to 0-5 scale)
- More rented movies score better
- Weight: 30% of final score

#### Demographics Score
- Based on similarity to users who liked the movie
- Combines age similarity (50%) and gender similarity (50%)
- Weight: 30% of final score

### Age Similarity Calculation

```
age_similarity = 1 - min(age_difference / 20, 1)
```

- 0 years difference = 100% similarity
- 10 years difference = 50% similarity  
- 20+ years difference = 0% similarity

### Gender Similarity Calculation

```
gender_similarity = same_gender_users / total_users_who_liked_movie
```

- If all users who liked a movie are same gender = 100% similarity
- If half are same gender = 50% similarity
- If no matching gender users = 0% similarity

### Confidence Calculation

```
confidence = (normalized_quality × 0.4) + (normalized_popularity × 0.3) + (demographics × 0.3)
```

## Performance Considerations

### Scalability
- Efficient database queries with proper indexing
- Caching for frequent users reduces computation
- Batch processing of user data

### Cold Start Problem
- New users: Uses simplified approach with genre preferences
- New movies: Recommended based on initial ratings and genre
- System improves as more demographic data is collected

### Data Requirements
- Works best when users have birth_date and gender filled
- Falls back gracefully when demographic data is missing
- Minimum 5 user interactions to be considered "frequent user"

## Monitoring and Maintenance

### Model Performance Metrics
- Track recommendation click-through rates
- Monitor user engagement with recommended movies
- Compare quality/popularity/demographics contribution

### Retraining Strategy
- No ML model retraining needed (rule-based approach)
- Monitor recommendation diversity
- Adjust scoring weights based on user feedback

## Advantages Over Previous Approach

### 1. Transparency
- Clear, interpretable scoring system
- Easy to explain why movies are recommended
- No "black box" ML model

### 2. Demographic Awareness
- Considers user age and gender preferences
- Recommends movies popular with similar users
- Better personalization for diverse user base

### 3. Balanced Approach
- Combines objective quality (ratings)
- Subjective popularity (rentals)
- Personal preferences (demographics)

### 4. Performance
- No ML training required
- Faster recommendations
- Lower resource usage

### 5. Maintainability
- Easier to debug and improve
- Simple to adjust weights and parameters
- No dependency on ML libraries

## Future Enhancements

1. **Content-Based Filtering**: Add movie content analysis (genre, director, actors)
2. **Temporal Patterns**: Consider time-based preferences (weekend vs weekday)
3. **Social Features**: Incorporate friend recommendations
4. **Mood Detection**: Detect user mood from interaction patterns
5. **A/B Testing**: Test different scoring weight combinations

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

**Slow recommendations:**
- Check database indexing on ratings and rentals tables
- Verify caching is working for frequent users
- Consider pre-computing some scores

## Dependencies

- Laravel framework
- Standard PHP extensions
- Database with proper indexing

## License

This recommendation system is part of the main application and inherits its license (MIT).