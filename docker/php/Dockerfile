FROM php:8.3-fpm

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libicu-dev \
        unzip \
        git \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        intl \
        zip \
        pcntl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-sge.conf

# Valores por defecto de desarrollo; en staging se sobrescriben por variables de entorno.
ENV FPM_MAX_CHILDREN=20 \
    FPM_START_SERVERS=6 \
    FPM_MIN_SPARE=4 \
    FPM_MAX_SPARE=10 \
    PHP_MEMORY_LIMIT=128M

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd --force --gid ${GID} sge \
    && useradd --uid ${UID} --gid ${GID} --shell /bin/bash --create-home sge

WORKDIR /var/www/html

USER sge
