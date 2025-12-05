FROM php:8.3-fpm-alpine

# Instala las dependencias del sistema y extensiones de PHP que Laravel necesita.
RUN apk add --no-cache \
    nginx \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    postgresql-client \
    postgresql-dev \
    git \
    supervisor \
    openssl \
    curl \
    oniguruma-dev \
    sqlite-dev \
    zip \
    unzip \
    nodejs \
    npm \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql gd exif mbstring pcntl posix \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Instala Composer globalmente.
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copia el código de tu proyecto al contenedor.
COPY . /var/www/html
# Copia la configuración de Nginx adecuada (por defecto la de docker-compose)
RUN mkdir -p /etc/nginx/conf.d
ARG NGINX_CONF=default
RUN CONF_PATH=/var/www/html/docker/nginx/${NGINX_CONF}.conf; \
    if [ ! -f "$CONF_PATH" ]; then CONF_PATH=/var/www/html/docker/nginx/default.conf; fi; \
    cp "$CONF_PATH" /etc/nginx/conf.d/default.conf

# Configura los permisos para los directorios de almacenamiento y caché de Laravel.
RUN chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Default expose remains 9000 for php-fpm; Render overrides via entrypoint script.
EXPOSE 9000

# Copia y usa un entrypoint que arranca Laravel automáticamente
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
CMD ["/usr/local/bin/entrypoint.sh"]
