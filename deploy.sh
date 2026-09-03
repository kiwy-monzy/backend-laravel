#!/bin/bash
set -e

echo "==> Switching to production environment..."
sed -i 's/APP_ENV=local/APP_ENV=production/' .env
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env

echo "==> Generating app key..."
php artisan key:generate --force

echo "==> Generating JWT secret..."
php artisan jwt:secret --force

echo "==> Clearing cache..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo "==> Caching configuration..."
php artisan config:cache
php artisan route:cache

echo "==> Running migrations..."
php artisan migrate --force --no-interaction

echo "==> Optimizing..."
php artisan optimize

echo "==> Setting permissions..."
chmod -R 775 storage bootstrap/cache

echo "==> Deployment complete!"
