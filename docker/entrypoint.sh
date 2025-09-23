#!/bin/sh
set -e

# Ensure vendor and node modules if needed (Composer only for now)
if [ ! -d "/var/www/html/vendor" ]; then
  echo "Installing PHP dependencies with Composer..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Ensure .env exists
if [ ! -f "/var/www/html/.env" ]; then
  echo "Creating .env from example..."
  cp /var/www/html/.env.example /var/www/html/.env || true
fi

# Generate app key if missing
if ! grep -q "^APP_KEY=base64:" /var/www/html/.env; then
  echo "Generating APP_KEY..."
  php artisan key:generate --force
fi

# Wait for Postgres
if [ -n "$DB_HOST" ]; then
  echo "Waiting for Postgres at $DB_HOST:$DB_PORT..."
  until php -r "try{new PDO('pgsql:host='.'$DB_HOST'.';port='.'${DB_PORT:-5432}'.';dbname='.'$DB_DATABASE'.';','${DB_USERNAME}','${DB_PASSWORD}');echo 'OK';}catch(Exception $e){exit(1);}"
  do
    echo "DB not ready, retrying in 2s..."
    sleep 2
  done
fi

# Run migrations (safe if already ran)
php artisan migrate --force || true

# Cache config/routes/views for speed (ignore failures in dev)
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Start PHP-FPM in foreground (Nginx will proxy to it)
exec php-fpm -F
