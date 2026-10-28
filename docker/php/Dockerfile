# Versión de parche fija: 8.3.36 corrige PHP2Shell (CVE-2026-109618/109619, RCE).
# Subirla a mano en cada release de seguridad de PHP; nunca bajar de aquí.
FROM php:8.3.36-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libpq \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    oniguruma-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        zip \
        pcntl \
        bcmath \
        opcache

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# Mitigación adicional de CVE-2026-109619; Laravel no usa upload_progress.
RUN echo 'session.upload_progress.enabled = 0' > "$PHP_INI_DIR/conf.d/zz-security.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

COPY . .
RUN composer run-script post-autoload-dump || true

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
