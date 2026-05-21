FROM php:8.4-apache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y \
    libpq-dev \
    git \
    unzip \
    zip \
    && docker-php-ext-install \
    pdo_pgsql \
    sockets \
    bcmath

# Исправлено: меняем пути в обоих конфигах, чтобы избежать ошибки 403 в PHP 8.4
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/apache2.conf

RUN a2enmod rewrite

COPY docker/php/entrypoint.sh /usr/local/bin/
COPY docker/php/entrypoint-worker.sh /usr/local/bin/

RUN chmod +x \
    /usr/local/bin/entrypoint.sh \
    /usr/local/bin/entrypoint-worker.sh
    
COPY src /var/www/html

WORKDIR /var/www/html

# Оставляем это здесь (полезно для продакшн-сборок), но вentrypoint добавим подстраховку для локалки
RUN composer install --no-interaction --prefer-dist --no-scripts