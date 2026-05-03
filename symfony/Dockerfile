FROM php:8.4-apache AS base

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libicu-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        intl \
        opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/app/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /app

FROM base AS builder

ENV APP_ENV=prod

RUN apt-get update && apt-get install -y git unzip libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && composer run-script post-install-cmd --no-dev

FROM base AS production

WORKDIR /app

COPY --from=builder /app /app

RUN chown -R www-data:www-data /app/var

EXPOSE 80
