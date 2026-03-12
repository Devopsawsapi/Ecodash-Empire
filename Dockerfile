# ════════════════════════════════════════════════════════════════
# EcoDash — Dockerfile Railway (PHP 8.2 / Laravel 12)
# Optimisé : layer cache sur vendor/ pour éviter les timeouts EOF
# ════════════════════════════════════════════════════════════════
FROM dunglas/frankenphp:php8.2-bookworm

LABEL maintainer="Empire TechNova" app="EcoDash"

# ── Paquets système ──────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    ca-certificates git unzip zip curl \
    && rm -rf /var/lib/apt/lists/*

# ── Extensions PHP ───────────────────────────────────────────────
RUN install-php-extensions \
    ctype curl dom fileinfo filter hash \
    mbstring openssl pcre pdo pdo_mysql \
    session tokenizer xml opcache intl zip

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
# On copie UNIQUEMENT composer.json + composer.lock + artisan
# → si ces fichiers ne changent pas, Docker réutilise le cache
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
CMD php setup.php && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
