#!/bin/bash
set -e

echo "=== Starting Notenux Application Setup ==="
date

APP_DIR="/home/u916682966/domains/notenux.oryphem.com/notenux"
cd "$APP_DIR"

export PATH="$HOME/bin:$PATH"

echo "--- Installing Composer dependencies ---"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo "--- Running database migrations ---"
php artisan migrate --force --no-interaction

echo "--- Optimizing Laravel cache ---"
php artisan optimize:clear
php artisan optimize

echo "--- Setting permissions & symlinks ---"
if [ ! -e public/storage ] && [ ! -L public/storage ]; then
    php artisan storage:link
fi
chmod -R 775 storage bootstrap/cache
ln -sfn "$APP_DIR/public" "/home/u916682966/domains/notenux.oryphem.com/public_html"

echo "=== Notenux Application Setup Completed ==="
