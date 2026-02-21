<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About This Project

This Laravel application implements two machine learning systems:

1. **Iris Flower Classification System** - Classifies iris flowers into species using Naive Bayes
2. **Movie Recommendation System** - Provides personalized movie recommendations using KNN regression

Both systems are built with Rubix ML and integrated into a Laravel web application.

## Installation

### Prerequisites
- PHP 8.1+
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

**Iris Model:**
```bash
php artisan iris:train
```

**Movie Recommendation Model:**
```bash
php artisan recommendations:retrain
```

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

## API Endpoints

### Iris Classification
- `POST /api/iris/predict` - Classify iris flower species

### Movie Recommendations
- `GET /api/recommendations/personalized` - Personalized recommendations (auth required)
- `GET /api/recommendations/popular` - Popular movies (auth required)
- `GET /api/recommendations/popular-public` - Public popular movies
- `POST /api/recommendations/retrain` - Retrain recommendation model (auth required)

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

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
