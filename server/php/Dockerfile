# Build Stage
ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-fpm AS build

# Quraşdırılacaq sistem asılılıqları
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    libpng-dev \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    zip \
    curl \
    unzip \
    git && \
    docker-php-ext-install \
        pdo \
        pdo_mysql \
        mysqli \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        pdo_pgsql \
        opcache && \
    docker-php-ext-configure pgsql --with-pgsql=/usr/local/pgsql && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* && \
    # Composer-i quraşdırırıq
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Runtime Stage
FROM php:${PHP_VERSION}-fpm

# Faylları build mərhələsindən kopyalayırıq
COPY --from=build /usr/local/bin/composer /usr/local/bin/composer
COPY --from=build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=build /usr/local/bin/docker-php-ext-enable /usr/local/bin/

# Yalnız lazım olan runtime asılılıqlarını quraşdırırıq
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# PHP uzantılarını aktivləşdiririk
RUN docker-php-ext-enable \
    pdo \
    pdo_mysql \
    mysqli \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    pdo_pgsql \
    opcache

# Xüsusi PHP konfiqurasiyasını kopyalayırıq
COPY ./server/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

# İşçi direktoriyasını müəyyən edirik
WORKDIR /var/www/html/be

USER root
# Tətbiq kodunu kopyalayırıq və icazələri təyin edirik
COPY --chown=www-data:www-data ./src/be /var/www/html/be
RUN chown -R www-data:www-data /var/www/html/be/storage /var/www/html/be/bootstrap/cache && \
    chmod -R 777 /var/www/html/be/storage /var/www/html/be/bootstrap/cache

# Composer install/update əmri
RUN if [ -f "composer.lock" ]; then \
        composer install --no-dev --optimize-autoloader; \
    else \
        composer update --no-dev --optimize-autoloader; \
    fi && \
    composer clear-cache

# PHP log faylları üçün direktoriyanı yaradırıq
RUN mkdir -p /var/log/php && \
    chown -R www-data:www-data /var/log/php && \
    chmod 755 /var/log/php

# Non-root istifadəçisinə keçirik
USER www-data

# Sağlamlıq yoxlaması (Healthcheck) əlavə edirik
HEALTHCHECK --interval=30s --timeout=30s --start-period=5s --retries=3 \
    CMD php-fpm -t || exit 1

# PHP-FPM portunu expose edirik
EXPOSE 9000

# PHP-FPM-i başlatırıq
CMD ["php-fpm"]