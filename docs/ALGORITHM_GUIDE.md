# Algorithm Guide

## Machine Learning Algorithms Overview

This guide explains the algorithms used in our ML systems and NLP chatbot.

## Algorithm Comparison

| System | Algorithm | Supervised | Key Strengths |
|---------|-----------|------------|---------------|
| Anomaly Detection | Isolation Forest | ❌ No | Explains anomalies, unsupervised |
| Iris Classification | Naive Bayes | ✅ Yes | Simple, probabilistic |
| Recommendations | KNN | ✅ Yes | Intuitive, instance-based |
| Chatbot NLP | KNN + Rules | ✅ Yes | Multilingual, context-aware |

## 1. Anomaly Detection System

### Isolation Forest

**Type**: Unsupervised Anomaly Detection

**How it works**:
1. Randomly selects a feature and splits the data
2. Recursively isolates observations by randomly selecting features and splits
3. Anomalies require fewer splits to isolate (shorter path length)
4. Anomaly score = path length normalized by tree depth

**Key Properties**:
- Unsupervised: Doesn't require labeled anomaly data
- Efficient: O(n) training, O(1) prediction
- Interpretable: Path length explains why an observation is anomalous
- Scalable: Works well with high-dimensional data

**Use Cases**:
- Fraud detection
- Outlier identification
- Novelty detection
- Anomaly explanation

**Threshold**: 0.3 (configurable)

### Detection Command

```bash
php artisan anomaly:detect
```

**Command Options**:
- `--retrain` - Force model retraining
- `--check-user=ID` - Check specific user ID

**Usage Examples**:

```bash
# Run anomaly detection
php artisan anomaly:detect

# Force retraining and detect
php artisan anomaly:detect --retrain

# Check specific user
php artisan anomaly:detect --check-user=1
```

**Model Location**: `storage/app/anomaly_detector.model`

**Dataset**: Uses user behavior data from the database

**When to Use**:
- Regular monitoring for unusual activity
- Fraud detection
- User behavior analysis
- System health monitoring

### Rule-Based Detection

**Complementary Approach**:
```
IF rentals_per_day > 0.5 THEN high_frequency
IF rating_stddev > 1.5 THEN inconsistent_ratings
IF late_returns > 30% THEN frequent_late_returns
```

**Use Cases**:
- Simple, explainable thresholds
- Complements ML model scores
- Catches obvious anomalies
- Easy to audit and adjust

### Hybrid Approach

**Best of both worlds**:
- ML model catches complex patterns
- Rules catch obvious anomalies
- Combined score provides comprehensive detection
- Adjustable sensitivity via thresholds

## 2. Iris Flower Classification

### Naive Bayes

**Type**: Supervised Classification

**How it works**:
1. Calculates probability of each class given features
2. Uses Bayes' theorem to combine probabilities
3. Predicts class with highest posterior probability

**Key Properties**:
- Simple and fast
- Works well with categorical data
- Probabilistic output
- Assumes feature independence

**Use Cases**:
- Multi-class classification
- Text classification
- When feature independence assumption holds

### Training Command

The Iris classification model is trained automatically when predictions are made:

```bash
# No separate training command - model trains on first prediction
# The model is automatically persisted to storage/app/iris.model
```

**Model Location**: `storage/app/iris.model`

**Dataset**: Uses the built-in Iris dataset from `storage/app/iris.csv`

**When Training Occurs**:
- First prediction request
- When model file doesn't exist
- Automatic persistence after training

## 3. Movie Recommendation System

### K-Nearest Neighbors (KNN) Regressor

**Type**: Supervised Regression

**How it works**:
1. Finds k most similar users (neighbors)
2. Averages their ratings for prediction
3. Uses cosine similarity for distance

**Key Properties**:
- Instance-based learning
- Simple and intuitive
- Works well with small datasets
- Sensitive to feature scaling

**Use Cases**:
- Collaborative filtering
- Content-based recommendations
- When neighbors are meaningful

### Training Command

```bash
php artisan recommendations:train
```

**Command Options**:
- `--force` - Force retraining even if model exists

**Usage Examples**:

```bash
# Train recommendation model
php artisan recommendations:train

# Force retraining
php artisan recommendations:train --force
```

**Model Location**: `storage/app/movie_recommender.model`

**Dataset**: Uses movie ratings from the database

**When to Use**:
- Initial setup
- After adding new movies or ratings
- Periodic retraining for improved accuracy

