# ── Stage: PHP 8.1 com todas as extensões necessárias ─────────────────────────
FROM php:8.2-cli

# Dependências do sistema + extensões PHP (instaladas UMA VEZ no build)
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    libonig-dev \
    unzip \
    curl \
    git \
    && docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    zip \
    gd \
    mbstring \
    xml \
    opcache \
    && rm -rf /var/lib/apt/lists/*

# Composer global
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

EXPOSE 8000

# O código é montado como volume em dev (docker-compose).
# O entrypoint roda composer install e inicia o servidor.
CMD ["php", "-S", "0.0.0.0:8000", "router.php"]