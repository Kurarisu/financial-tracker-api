FROM php:8.2-fpm

# Install system dependencies (including libpq-dev for PostgreSQL)
RUN apt-get update && apt-get install -y \
    git \
    tesseract-ocr \
    tesseract-ocr-ind \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required by Laravel & PostgreSQL
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    pdo_mysql \
    pdo_sqlite \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Allow receipt uploads up to Laravel's 10 MB validation limit, including multipart overhead.
RUN printf 'upload_max_filesize=12M\npost_max_size=13M\n' > /usr/local/etc/php/conf.d/uploads.ini

# Expose port 9000 for PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]
