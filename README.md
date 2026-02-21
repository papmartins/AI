<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About This Project

This Laravel application implements multiple machine learning systems:

1. **Iris Flower Classification System** - Classifies iris flowers into species using Naive Bayes
2. **Movie Recommendation System** - Provides personalized movie recommendations using KNN regression
3. **Anomaly Detection System** - Identifies anomalous user behavior using Isolation Forest
4. **Model Training Center** - Centralized interface for training and monitoring all ML models

## Algorithm Guide

For detailed algorithm explanations, see [docs/ALGORITHM_GUIDE.md](docs/ALGORITHM_GUIDE.md)

All systems are built with Rubix ML and integrated into a Laravel web application with Inertia.js frontend.

## 🚀 Project Features

### 🎯 Core Machine Learning Systems

#### 1. Iris Flower Classification
- **Algorithm**: Naive Bayes classifier
- **Input**: Sepal and petal measurements
- **Output**: Iris species (setosa, versicolor, virginica)
- **Accuracy**: Typically 95%+ on test data
- **Use Case**: Demonstration of basic classification

#### 2. Movie Recommendation System
- **Algorithm**: K-Nearest Neighbors (KNN) regression
- **Input**: User ratings, movie features, viewing history
- **Output**: Personalized movie recommendations with confidence scores
- **Features**:
  - Collaborative filtering
  - Content-based recommendations
  - Hybrid approach
  - Real-time predictions
- **Use Case**: Personalized content discovery

#### 3. Anomaly Detection System
- **Algorithm**: Isolation Forest
- **Input**: User behavior patterns, rental history, rating patterns
- **Output**: Anomaly scores and flagged users
- **Features**:
  - Real-time anomaly detection
  - Multiple anomaly types (high frequency, late returns, suspicious ratings)
  - Configurable sensitivity thresholds
  - Historical analysis
- **Use Case**: Fraud detection, unusual behavior identification

#### 4. Model Training Center
- **Purpose**: Centralized interface for all ML model management
- **Features**:
  - Real-time training status monitoring
  - Individual or batch model training
  - Training time tracking and performance metrics
  - Progress indicators and visual feedback
  - Training history and model versioning
- **Benefits**: Simplified model management, performance monitoring, easy retraining

### 🎨 Web Interface Features

#### User Management
- **Registration & Authentication**: Secure user accounts with email verification
- **Profile Management**: Update personal information and preferences
- **Account Settings**: Password changes and account deletion

#### Movie Management
- **Browse Movies**: Search and filter movies by genre, year, rating
- **Movie Details**: Comprehensive information with ratings and reviews
- **Rating System**: 1-5 star rating with personalization
- **Wishlist**: Save favorite movies for later

#### Rental System
- **Movie Rentals**: Rent movies with due dates and return tracking
- **Rental History**: View past and current rentals
- **Late Returns**: Automatic detection and notifications

#### Recommendation Features
- **Personalized Recommendations**: Based on user preferences and history
- **Popular Recommendations**: Trending movies across all users
- **Recommendation Explanations**: Understand why items are recommended

#### Anomaly Detection Dashboard
- **Anomaly Overview**: Summary of detected anomalies
- **User-Specific Anomalies**: Detailed view of anomalous users
- **Anomaly Resolution**: Mark anomalies as resolved
- **Statistics**: Historical trends and patterns

#### Admin Features
- **User Management**: View and manage all users
- **System Monitoring**: Health checks and performance metrics
- **Data Export**: Export data for analysis

### 🔧 Technical Features

#### Backend
- **Laravel 12.x**: Modern PHP framework
- **Inertia.js**: Seamless frontend-backend integration
- **RESTful API**: Well-structured endpoints
- **Authentication**: Sanctum for API, Session for web
- **Database**: MySQL with Eloquent ORM
- **Caching**: Redis-ready architecture
- **Queues**: Job processing for background tasks

#### Frontend
- **Vue 3**: Composition API
- **Tailwind CSS**: Utility-first styling
- **Responsive Design**: Mobile-friendly interface
- **Real-time Updates**: Live data without refresh
- **Accessibility**: WCAG compliant components

#### Machine Learning
- **Rubix ML**: PHP machine learning library
- **Model Persistence**: Save/load trained models
- **Feature Engineering**: Automatic data preparation
- **Model Evaluation**: Performance metrics
- **Hyperparameter Tuning**: Optimized configurations

#### Testing
- **PHPUnit**: Comprehensive test coverage
- **Feature Tests**: End-to-end workflow testing
- **Unit Tests**: Isolated component testing
- **Test Database**: Isolated testing environment
- **CI Ready**: Automated testing pipeline

## Installation

### Prerequisites
- PHP 8.3+
- Composer
- Node.js (for frontend assets)
- MySQL or other database supported by Laravel
- Rubix ML PHP extension
- Docker (optional, for containerized setup)

### Setup Instructions

#### Option 1: Traditional Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/your-repo/AI.git
   cd AI
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Install JavaScript dependencies:**
   ```bash
   npm install
   ```

4. **Set up environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure database in `.env`:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ai
   DB_USERNAME=root
   DB_PASSWORD=
   ```

6. **Run migrations and seeders:**
   ```bash
   php artisan migrate --seed
   ```

7. **Build frontend assets:**
   ```bash
   npm run build
   ```

8. **Start development server:**
   ```bash
   php artisan serve
   ```

9. **Access the application:**
   Open http://localhost:8000 in your browser

#### Option 2: Docker Installation (Recommended)

1. **Clone the repository:**
   ```bash
   git clone https://github.com/your-repo/AI.git
   cd AI
   ```

2. **Copy environment file:**
   ```bash
   cp .env.example .env
   ```

3. **Configure database in `.env` for Docker:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=db
   DB_PORT=3306
   DB_DATABASE=laravel
   DB_USERNAME=laravel
   DB_PASSWORD=secret
   ```

4. **Build and start containers:**
   ```bash
   docker-compose up -d --build
   ```

5. **Install dependencies inside containers:**
   ```bash
   docker-compose exec app composer install
   docker-compose exec node npm install
   ```

6. **Generate application key:**
   ```bash
   docker-compose exec app php artisan key:generate
   ```

7. **Run migrations and seeders:**
   ```bash
   docker-compose exec app php artisan migrate --seed
   ```

8. **Build frontend assets:**
   ```bash
   docker-compose exec node npm run build
   ```

9. **Access the application:**
   Open http://localhost:8080 in your browser

10. **For development with hot reload:**
    ```bash
    docker-compose exec node npm run dev
    ```

### Additional Setup for ML Systems

The application includes pre-trained models, but you can retrain them:

**Iris Flower Classification Model:**
```bash
php artisan iris:train
```

**Movie Recommendation Model:**
```bash
php artisan recommendations:train
```

**Anomaly Detection Model:**
```bash
php artisan anomaly:detect --retrain
```

Or use the **Model Training Center** (recommended):
- Navigate to the 🤖 Model Training page
- Click "Train All Models" to retrain everything at once
- View training times and status in real-time
php artisan recommendations:retrain

## Project Features

### Iris Flower Classification System
- Classifies iris flowers into 3 species (setosa, versicolor, virginica)
- Uses Naive Bayes classifier with 90-95% accuracy
- Real-time predictions via API endpoint
- Automatic model training on first use

**Documentation:** [IRIS_PREDICTION_SYSTEM.md](docs/IRIS_PREDICTION_SYSTEM.md)

### Movie Recommendation System
- Personalized movie recommendations using KNN regression
- 4-feature architecture: rental percentage, average rental age, average rating, average good rating age
- Age-appropriate filtering based on user age
- Confidence scoring for recommendation quality
- Smart caching for frequent users
- Fallback to popularity-based recommendations

**Documentation:** [RECOMMENDATION_SYSTEM.md](docs/RECOMMENDATION_SYSTEM.md)

### Anomaly Detection System
- Advanced user behavior analysis using Isolation Forest algorithm
- Detects multiple anomaly types:
  - **High Rental Frequency**: Users renting at unusually high rates
  - **Inconsistent Ratings**: Users with highly variable rating patterns
  - **Frequent Late Returns**: Users who consistently return movies late
  - **General Suspicious Activity**: Other unusual behavior patterns
- Real-time anomaly scoring and classification
- Comprehensive anomaly dashboard with filtering and resolution
- Configurable sensitivity thresholds
- Historical anomaly tracking and trends

**Documentation:** [ANOMALY_DETECTION_SYSTEM.md](docs/ANOMALY_DETECTION_SYSTEM.md)

## API Endpoints

### Iris Classification
- `POST /api/iris/predict` - Classify iris flower species

### Movie Recommendations
- `GET /api/recommendations/personalized` - Personalized recommendations (auth required)
- `GET /api/recommendations/popular` - Popular movies (auth required)
- `GET /api/recommendations/popular-public` - Public popular movies
- `POST /api/recommendations/retrain` - Retrain recommendation model (auth required)

### Anomaly Detection

#### Detection
- `GET /api/anomaly` - Get all detected anomalies
- `GET /api/anomaly/check-me` - Check if current user is anomalous
- `GET /api/anomaly/stats` - Get anomaly statistics

#### Management
- `POST /api/anomaly/resolve/{anomalyId}` - Mark anomaly as resolved
- `POST /api/anomaly/retrain` - Retrain anomaly detection model

#### User-Specific
- `GET /api/anomaly/users/{userId}` - Get anomalies for specific user
## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Model Training Center

The application includes a comprehensive **Model Training Center** that allows you to train and manage all machine learning models from a single, user-friendly interface.

### Features

- **Real-time Status Monitoring**: View which models are trained and their current status
- **Training Controls**: Train individual models or all models at once
- **Performance Metrics**: See exact training times for each model
- **Progress Indicators**: Visual feedback during training operations
- **Training History**: View when models were last trained and their sizes

### Accessing the Model Training Center

1. **Navigate to the page**: Click the 🤖 **Model Training** link in the main navigation
2. **Check model status**: See which models are trained and when they were last updated
3. **Train models**: Click the appropriate button to train:
   - **Train Recommendation Model** - Trains the movie recommendation model
   - **Train Anomaly Detection Model** - Trains the anomaly detection model
   - **Train All Models** - Trains both models sequentially
4. **Monitor progress**: Watch the progress indicator during training
5. **View results**: See updated status and training times after completion

### Training Time Information

The system now displays detailed training times:
- **Recommendation Model**: Shows time taken to train the KNN recommendation model
- **Anomaly Detection Model**: Shows time taken to train the Isolation Forest anomaly detector
- **Total Training Time**: Shows combined time when training all models

Example output:
```
Last Training Times
Recommendation Model: 12.45 seconds
Anomaly Detection Model: 8.72 seconds
Total Training Time: 21.17 seconds
```

### Technical Details

- **Inertia.js** for seamless page transitions
- **Vue 3** with Composition API for reactive UI
- **Tailwind CSS** for responsive styling
- **Real-time updates** without page refresh
- **Session-based tracking** of training times
- **Protected routes** - Only authenticated users can access

### API Endpoints

The Model Training Center uses these API endpoints:
- `GET /model-training` - Main page
- `POST /model-training/train-recommendation` - Train recommendation model
- `POST /model-training/train-anomaly` - Train anomaly detection model
- `POST /model-training/train-all` - Train all models
- `GET /model-training/status` - Get training status (JSON)

### Best Practices

1. **Train during low-traffic periods**: Model training can be resource-intensive
2. **Monitor training times**: Use the displayed times to optimize your workflow
3. **Retrain periodically**: As your dataset grows, retrain models for better accuracy
4. **Check logs**: Training errors are logged for troubleshooting
5. **Use the status API**: Integrate with monitoring systems using the JSON endpoint

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
