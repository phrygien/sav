# ─────────────────────────────────────────────
# Stage 1 : dépendances PHP (Composer)
# ─────────────────────────────────────────────
FROM php:8.4-fpm AS composer-builder

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    git \
    libicu-dev \
    libzip-dev \
    libonig-dev \
    && docker-php-ext-install -j$(nproc) intl zip mbstring \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .

# On supprime les caches Laravel copiés depuis la machine locale
# (ils référencent des packages de dev absents ici, ex. laravel/boost)
RUN rm -f bootstrap/cache/*.php \
    && composer dump-autoload --optimize --no-dev

# ─────────────────────────────────────────────
# Stage 2 : build des assets front (Vite)
# ─────────────────────────────────────────────
FROM node:22-slim AS node-builder

WORKDIR /app

COPY package*.json ./
RUN npm ci || npm install

# Code complet + vendor déjà installé (pour que Tailwind scanne vendor/)
COPY --from=composer-builder /app ./

RUN npm run build

# ─────────────────────────────────────────────
# Stage 3 : image finale (PHP-FPM + Nginx + Supervisor)
# ─────────────────────────────────────────────
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    zip \
    unzip \
    curl \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && rm -rf /var/lib/apt/lists/*

# Config PHP (uploads, memory_limit, OPcache) et pool PHP-FPM (60 workers)
COPY docker/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-www.conf

WORKDIR /var/www/html

COPY --from=composer-builder /app ./
COPY --from=node-builder /app/public/build ./public/build

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
