FROM php:8.2-fpm

# تثبيت dependencies
RUN apt-get update && apt-get install -y \
    curl \
    gnupg2 \
    zip \
    unzip \
    git \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    pkg-config \
    zlib1g-dev \
    libicu-dev \
    g++ \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        zip \
        intl \
        gd

# Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

# تثبيت dependencies
RUN composer install --no-dev --optimize-autoloader

# صلاحيات
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www

EXPOSE 9000

CMD ["php-fpm"]
