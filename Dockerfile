# ExamNex — Laravel app image (PHP 8.4 + Apache)

# --- Stage 1: compile Tailwind CSS (no CDN → no flash/reflow) ---
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json tailwind.config.js ./
RUN npm install
COPY resources ./resources
RUN npx tailwindcss -i resources/css/app.css -o public/css/app.css --minify

# --- Stage 2: PHP app ---
FROM php:8.4-apache

# System deps + PHP extensions Laravel/MySQL need
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libicu-dev libonig-dev libpng-dev libjpeg-dev libfreetype6-dev \
        unzip default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring zip intl gd bcmath exif opcache \
    && rm -rf /var/lib/apt/lists/*

# --- OPcache + JIT (big PHP speed boost; code is baked so timestamp checks off) ---
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=0'; \
        echo 'opcache.memory_consumption=192'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.jit=1255'; \
        echo 'opcache.jit_buffer_size=64M'; \
        echo 'realpath_cache_size=4096K'; \
        echo 'realpath_cache_ttl=600'; \
        echo 'memory_limit=256M'; \
    } > /usr/local/etc/php/conf.d/zz-perf.ini

# Composer (from the official image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Serve Laravel's public/ directory + enable mod_rewrite
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite deflate expires headers \
    && sed -i 's/Listen 80/Listen 8000/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:8000>/' /etc/apache2/sites-available/000-default.conf \
    && printf '%s\n' \
        '<IfModule mod_deflate.c>' \
        '  AddOutputFilterByType DEFLATE text/html text/plain text/css application/javascript application/json image/svg+xml' \
        '</IfModule>' \
        '<IfModule mod_expires.c>' \
        '  ExpiresActive On' \
        '  ExpiresByType text/css "access plus 2 hours"' \
        '  ExpiresByType application/javascript "access plus 2 hours"' \
        '  ExpiresByType image/png "access plus 30 days"' \
        '  ExpiresByType image/jpeg "access plus 30 days"' \
        '  ExpiresByType image/svg+xml "access plus 30 days"' \
        '  ExpiresByType image/webp "access plus 30 days"' \
        '</IfModule>' > /etc/apache2/conf-available/perf.conf \
    && a2enconf perf

WORKDIR /var/www/html

# Install PHP deps first (better layer caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# App source
COPY . .
# Compiled Tailwind stylesheet from the assets stage
COPY --from=assets /app/public/css/app.css public/css/app.css
RUN cp .env.docker .env \
    && composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chmod +x docker-entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["./docker-entrypoint.sh"]
