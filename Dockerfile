FROM php:8.2-fpm-alpine

# Instalar dependencias del sistema
RUN apk add --no-cache \
    bash \
    curl \
    freetype-dev \
    g++ \
    gcc \
    git \
    icu-dev \
    icu-libs \
    libc-dev \
    libzip-dev \
    make \
    mysql-client \
    oniguruma-dev \
    openssh-client \
    rsync \
    zlib-dev

# Instalar extensiones PHP
RUN docker-php-ext-configure gd --with-freetype
RUN docker-php-ext-install \
    bcmath \
    gd \
    intl \
    mbstring \
    opcache \
    pdo \
    pdo_mysql \
    zip

# Instalar Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos de la aplicación
COPY src/ /var/www/html/src/
COPY templates/ /var/www/html/templates/
COPY composer.json composer.lock* ./

# Instalar dependencias PHP
RUN composer install --no-dev --optimize-autoloader

# Crear directorios necesarios
RUN mkdir -p /var/www/html/logs \
    && mkdir -p /var/www/html/storage/cache \
    && mkdir -p /var/www/html/storage/sessions

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configuración PHP
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Configuración PHP-FPM
COPY php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

EXPOSE 9000

CMD ["php-fpm"]
