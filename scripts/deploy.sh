#!/bin/bash
set -e

echo "=== Starting Notenux Application Setup ==="
date

APP_DIR="/home/u916682966/domains/notenux.oryphem.com/notenux"
mkdir -p "$APP_DIR"
cd "$APP_DIR"

if [ ! -f "$APP_DIR/.env" ] && [ -f "$APP_DIR/production.env" ]; then
    cp "$APP_DIR/production.env" "$APP_DIR/.env"
    echo "Created .env from production template."
fi

# Resolve PHP 8.5+ binary on Hostinger shared hosting
if [ -x "/opt/alt/php85/usr/bin/php" ]; then
    PHP_BIN="/opt/alt/php85/usr/bin/php"
elif [ -x "/opt/alt/php84/usr/bin/php" ]; then
    PHP_BIN="/opt/alt/php84/usr/bin/php"
elif [ -x "/usr/local/bin/php" ]; then
    PHP_BIN="/usr/local/bin/php"
else
    PHP_BIN="$(which php || echo 'php')"
fi

echo "Using PHP binary: $($PHP_BIN -v | head -n 1)"

export PATH="$HOME/bin:$PATH"

echo "--- Installing Composer dependencies ---"
if command -v composer &> /dev/null; then
    $PHP_BIN $(which composer) install --no-dev --prefer-dist --optimize-autoloader --no-interaction
elif [ -f "$HOME/bin/composer" ]; then
    $PHP_BIN "$HOME/bin/composer" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
else
    mkdir -p "$HOME/bin"
    curl -sS https://getcomposer.org/installer | $PHP_BIN -- --install-dir="$HOME/bin" --filename=composer
    $PHP_BIN "$HOME/bin/composer" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
fi

echo "--- Running database migrations ---"
$PHP_BIN artisan migrate --force --no-interaction

echo "--- Optimizing Laravel cache ---"
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

echo "--- Setting permissions & symlinks ---"
$PHP_BIN artisan storage:link || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
mkdir -p "/home/u916682966/domains/notenux.oryphem.com"
ln -sfn "$APP_DIR/public" "/home/u916682966/domains/notenux.oryphem.com/public_html"

echo "=== Notenux Application Setup Completed ==="
