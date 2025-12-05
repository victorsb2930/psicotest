#!/bin/sh
set -e

# Detect Render runtime (Render sets RENDER=true and always injects PORT). Allow manual override via RENDER_RUNTIME=1
if [ "${RENDER_RUNTIME:-}" = "" ]; then
  if [ "$RENDER" = "true" ] || [ "$RENDER" = "1" ] || [ -n "$PORT" ]; then
    RENDER_RUNTIME=1
  else
    RENDER_RUNTIME=0
  fi
fi

INSTALL_FLAGS="--no-interaction --prefer-dist --optimize-autoloader"
if [ "$RENDER_RUNTIME" = "1" ]; then
  INSTALL_FLAGS="$INSTALL_FLAGS --no-dev"
fi

# Ensure Composer dependencies (Render siempre reinstala para no dejar paquetes faltantes)
if [ "$RENDER_RUNTIME" = "1" ] || [ ! -d "/var/www/html/vendor" ] || [ "${FORCE_COMPOSER_INSTALL:-0}" = "1" ]; then
  echo "Installing PHP dependencies with Composer..."
  composer install $INSTALL_FLAGS
fi

# Ensure .env exists
if [ ! -f "/var/www/html/.env" ]; then
  echo "Creating .env from example..."
  cp /var/www/html/.env.example /var/www/html/.env || true
fi

# Ensure APP_KEY placeholder exists in .env so artisan command can fill it
if ! grep -q "^APP_KEY=" /var/www/html/.env; then
  echo "Appending APP_KEY placeholder to .env"
  printf '\nAPP_KEY=\n' >> /var/www/html/.env
fi

# Generate app key if missing locally and not provided via env
if ! grep -q "^APP_KEY=base64:" /var/www/html/.env; then
  if [ -n "$APP_KEY" ]; then
    echo "APP_KEY provided via environment; skipping key generation."
  else
    echo "Generating APP_KEY..."
    php artisan key:generate --force
  fi
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

# Install/build frontend assets during Render deploys (always build para asegurar manifest actualizado)
if [ "$RENDER_RUNTIME" = "1" ]; then
  cd /var/www/html
  echo "Installing npm dependencies..."
  npm ci
  echo "Building frontend assets..."
  npm run build
  cd - >/dev/null 2>&1 || cd /var/www/html
fi

# Ensure storage symlink (avoid copying host symlink in build context)
if [ ! -e public/storage ]; then
  echo "Creating storage symlink (public/storage)..."
  php artisan storage:link || true
fi

# Ensure cache directories exist and are writable at runtime
mkdir -p /var/www/html/storage/framework/views /var/www/html/storage/framework/cache /var/www/html/storage/logs /var/www/html/bootstrap/cache || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

# Cache config/routes/views for speed (ignore failures in dev)
php artisan optimize:clear || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Allow ad-hoc artisan commands via env (useful on hosts without shell access)
if [ -n "${ARTISAN_ON_BOOT:-}" ]; then
  echo "Running custom artisan command: php artisan ${ARTISAN_ON_BOOT}"
  if ! php artisan ${ARTISAN_ON_BOOT}; then
    echo "Custom artisan command failed; continuing startup" >&2
  fi
fi

# Adjust Nginx listen port if Render injected PORT
if [ "$RENDER_RUNTIME" = "1" ]; then
  LISTEN_PORT=${PORT:-8080}
  CONF_FILE=/etc/nginx/conf.d/default.conf
  if [ -f "$CONF_FILE" ]; then
    sed -i "s/fastcgi_pass app:9000;/fastcgi_pass 127.0.0.1:9000;/" "$CONF_FILE" || true
    if grep -q "listen 8080;" "$CONF_FILE"; then
      sed -i "s/listen 8080;/listen ${LISTEN_PORT};/" "$CONF_FILE" || true
    else
      sed -i "s/listen 80;/listen ${LISTEN_PORT};/" "$CONF_FILE" || true
    fi
  fi
fi

if [ "$RENDER_RUNTIME" = "1" ]; then
  echo "Starting php-fpm + nginx for Render..."
  php-fpm -D
  exec nginx -g 'daemon off;'
else
  # Default local dev: only php-fpm, nginx handled by separate container
  exec php-fpm -F
fi
