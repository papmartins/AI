FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libxml2-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    git \
    curl \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        xml \
        zip \
        gd \
        mbstring \
    && pecl install channel://pecl.php.net/svm-0.2.3 \
    && docker-php-ext-enable svm

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Add SVM extension configuration
RUN echo "extension=svm.so" > /usr/local/etc/php/conf.d/docker-php-ext-svm.ini

WORKDIR /var/www/html
