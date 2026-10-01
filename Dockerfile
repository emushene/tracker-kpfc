# ============================================
# Stage 1: Build frontend assets
# ============================================
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package*.json ./

RUN npm ci

COPY . .

RUN npm run build


# ============================================
# Stage 2: Install PHP dependencies
# ============================================
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts


# ============================================
# Stage 3: Production PHP-FPM application
# ============================================
FROM php:8.4-fpm-bookworm

WORKDIR /var/www/html


# --------------------------------------------
# System dependencies
# --------------------------------------------
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    unzip \
    git \
    curl \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        intl \
        zip \
        bcmath \
        opcache \
        pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*


# --------------------------------------------
# PHP dependencies
# --------------------------------------------
COPY --from=composer /app/vendor ./vendor


# --------------------------------------------
# Application source
# --------------------------------------------
COPY . .


# --------------------------------------------
# Laravel package discovery
# --------------------------------------------
RUN php artisan package:discover --ansi


# --------------------------------------------
# Compiled Vite assets
# --------------------------------------------
COPY --from=frontend /app/public/build ./public/build


# --------------------------------------------
# Preserve public directory template for volume syncing
# --------------------------------------------
RUN cp -a /var/www/html/public /var/www/html/public-template


# --------------------------------------------
# Entrypoint script
# --------------------------------------------
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh


# --------------------------------------------
# Laravel writable directories
# --------------------------------------------
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache


# --------------------------------------------
# Permissions
# --------------------------------------------
RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache \
    public


# --------------------------------------------
# PHP production configuration
# --------------------------------------------
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
    } > /usr/local/etc/php/conf.d/opcache.ini


# --------------------------------------------
# PHP-FPM
# --------------------------------------------
EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]