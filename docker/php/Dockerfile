FROM php:8.3.31-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpq-dev libzip-dev libonig-dev libfcgi-bin \
    && docker-php-ext-install pdo_pgsql mbstring zip pcntl opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.10.2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/health.conf /usr/local/etc/php-fpm.d/zz-health.conf

WORKDIR /var/www/html/backend
