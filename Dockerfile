FROM php:8.2-apache

RUN a2enmod rewrite

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    && docker-php-ext-install pdo_mysql mysqli zip \
    && pecl install redis && docker-php-ext-enable redis

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install PHP dependencies for WebSocket
RUN cd api && composer require cboden/ratchet --no-interaction 2>/dev/null || true

# Set permissions
RUN chown -R www-data:www-data /var/www/html/uploads 2>/dev/null || true

EXPOSE 80 8080

# Default command: Apache in background + WebSocket server
CMD service apache2 start && php api/ratchet_server.php
