FROM php:8.3-apache

# Dependencias del sistema
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Extensiones de PHP
RUN docker-php-ext-install pdo pdo_mysql sockets

# Extensión phpredis
RUN pecl install redis && docker-php-ext-enable redis

# Habilitar mod_rewrite de Apache si se requiere
RUN a2enmod rewrite

WORKDIR /var/www/html
COPY src/ /var/www/html/

RUN chown -R www-data:www-data /var/www/html
EXPOSE 80