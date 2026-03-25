#!/bin/sh

echo "⏳ Preparing Laravel..."

php artisan config:clear
php artisan cache:clear

# مهم جدًا
php artisan storage:link || true

echo "🚀 Starting PHP-FPM..."

php-fpm
