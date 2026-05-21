#!/bin/bash
set -e

cd /var/www/html

if [ ! -d "vendor" ]; then
  echo "Folder vendor not found. Running composer install..."
  composer install --no-interaction --prefer-dist
fi

if [ ! -f ".env" ]; then
  cp .env.example .env
fi


chmod -R 777 storage bootstrap/cache

php artisan key:generate --force
php artisan migrate --force


exec apache2-foreground