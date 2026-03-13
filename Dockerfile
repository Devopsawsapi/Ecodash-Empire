# ════════════════════════════════════════════════════════════════
# EcoDash — Dockerfile Railway (PHP 8.2 / Laravel 12)
# Optimisé : layer cache sur vendor/ pour éviter les timeouts EOF
# ════════════════════════════════════════════════════════════════
FROM php:8.2-cli-bookworm

LABEL maintainer="Empire TechNova" app="EcoDash"

# ── Paquets système ──────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    ca-certificates git unzip zip curl \
    libzip-dev libicu-dev libonig-dev libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# ── Extensions PHP ───────────────────────────────────────────────
RUN docker-php-ext-install \
    ctype dom fileinfo mbstring \
    pdo pdo_mysql opcache intl zip \
    xml bcmath pcntl

# ── PHP production settings ──────────────────────────────────────
RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.memory_consumption=128"; \
    echo "opcache.max_accelerated_files=10000"; \
    echo "opcache.validate_timestamps=0"; \
} >> /usr/local/etc/php/conf.d/opcache.ini

# ── Composer ─────────────────────────────────────────────────────
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# ── Étape 1 : installer les dépendances (cache layer séparé) ─────
COPY composer.json composer.lock artisan ./

RUN composer install \
    --optimize-autoloader \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist

# ── Étape 2 : copier le reste du code ────────────────────────────
COPY . .

# ── Permissions ──────────────────────────────────────────────────
RUN mkdir -p storage/logs \
             storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

# ── Démarrage : setup (migrations + seed) puis serve ─────────────
CMD bash -c "php setup.php && exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"