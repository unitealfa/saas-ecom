FROM composer:2 AS composer
FROM php:8.5-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates git unzip libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pcntl intl zip gd bcmath \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_HOME=/tmp/composer
CMD ["php", "-v"]
