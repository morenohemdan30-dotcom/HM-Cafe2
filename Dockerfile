# Base PHP 8.4 image
FROM php:8.4-cli-alpine

# Install system dependencies & PHP extensions needed for Symfony & MySQL
RUN apk add --no-cache \
    bash \
    git \
    icu-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    intl \
    opcache \
    zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for layer caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-interaction --optimize-autoloader --no-scripts

# Copy the rest of the application
COPY . /app

# Run composer scripts after full copy
RUN composer run-script post-install-cmd --no-interaction || true

# Set environment
ENV APP_ENV=dev
ENV PORT=8000
ENV DATABASE_URL="mysql://moreno:hemdan@database:3306/app_db?serverVersion=8.0&charset=utf8mb4"

# Expose Symfony port
EXPOSE 8000

# Startup: wait for DB, run migrations, then start server
CMD ["sh", "-c", "until php bin/console doctrine:query:sql 'SELECT 1' > /dev/null 2>&1; do echo 'Waiting for database...'; sleep 2; done && php bin/console doctrine:migrations:migrate --no-interaction && php -S 0.0.0.0:8000 -t public"]
