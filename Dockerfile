FROM php:8.2-fpm

# تحديث الحزم وتثبيت الأدوات الأساسية المطلوبة لكل extension
RUN apt-get update && apt-get install -y \
    curl \
    gnupg2 \
    zip \
    unzip \
    git \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    pkg-config \
    zlib1g-dev \
    libicu-dev \
    g++ \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath zip intl gd

# تثبيت Node.js + npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# تثبيت Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

# تثبيت الـ composer packages
RUN composer install --no-dev --optimize-autoloader

# صلاحيات الملفات
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www

EXPOSE 9000
CMD ["php-fpm"]
