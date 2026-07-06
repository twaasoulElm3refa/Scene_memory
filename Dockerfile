FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    curl \
    gnupg2 \
    zip \
    unzip \
    git \
    ffmpeg \
    imagemagick \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libavif-dev \
    libheif-dev \
    libtiff-dev \
    libmagickwand-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    pkg-config \
    zlib1g-dev \
    libicu-dev \
    g++ \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        zip \
        intl \
        gd \
    && pecl install redis imagick \
    && docker-php-ext-enable redis imagick \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000

CMD ["/entrypoint.sh"]
