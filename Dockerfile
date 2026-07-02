# ─────────────────────────────────────────────────────────────────────────────
# Laravel backend image for Railway
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.4-cli-bookworm

# System packages + PHP extensions Laravel needs (MySQL, mbstring, zip, bcmath)
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring bcmath zip opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer (from the official Composer image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP dependencies first for better layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Copy the rest of the application and finish autoloading
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8080

# On boot: discover packages, link storage, migrate, seed the admin (idempotent),
# cache config, build API docs, then serve on Railway's injected $PORT.
# NOTE: route:cache is intentionally omitted — routes/web.php uses a closure.
CMD sh -c "\
  php artisan package:discover --ansi || true; \
  php artisan storage:link || true; \
  php artisan migrate --force; \
  php artisan db:seed --class=AdminSeeder --force || true; \
  php artisan config:cache || true; \
  php artisan l5-swagger:generate || true; \
  php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"
