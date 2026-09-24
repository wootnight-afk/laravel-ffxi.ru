# ------------------------------------------------------------------
# PHP-FPM image for the local development stack (spec section 4).
# Not used in production: Timeweb shared hosting provides PHP 8.4.
# Matches spec section 4: dev PHP 8.4 = CI PHP 8.4 = production PHP 8.4.
# Extensions installed per spec section 7 (audit checklist) and
# spec section 12 (image processing via intervention/image).
# ------------------------------------------------------------------

FROM php:8.4-fpm

# Build arguments for the runtime user, matching the typical WSL2 host
# UID/GID (1000). Prevents ownership issues on bind-mounted project files.
ARG UID=1000
ARG GID=1000

# System libraries required to build the PHP extensions below.
# - libicu-dev          -> intl
# - libpng-dev          -> gd (png)
# - libjpeg62-turbo-dev -> gd (jpeg)
# - libfreetype6-dev    -> gd (freetype)
# - libzip-dev          -> zip
# - libonig-dev         -> mbstring
# - libxml2-dev         -> xml
# - git, unzip          -> Composer (dev only)
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions required by Laravel 13, Filament 5, Pest and
# intervention/image. pcntl and redis are intentionally NOT installed
# (spec sections 6, 32: no Redis, no long-running workers).
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pdo_mysql \
        zip

# Composer binary from the official image (dev only; production uses
# pre-built vendor/ shipped by rsync per spec section 22).
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Non-root runtime user, matches typical WSL2 host UID/GID.
# FPM pool is reconfigured to use this user instead of www-data so that
# files created by the container on bind mounts are owned by the host user.
RUN groupadd -g "${GID}" laravel \
    && useradd -u "${UID}" -g "${GID}" -m -s /bin/bash laravel \
    && sed -i 's/^user = www-data/user = laravel/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -i 's/^group = www-data/group = laravel/' /usr/local/etc/php-fpm.d/www.conf \
    && chown -R laravel:laravel /var/www

WORKDIR /var/www/html

USER laravel

EXPOSE 9000

CMD ["php-fpm"]