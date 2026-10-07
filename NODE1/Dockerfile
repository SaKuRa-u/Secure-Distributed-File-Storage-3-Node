FROM php:8.2-apache

# PostgreSQL driver (menggantikan mysqli/pdo_mysql) + curl untuk SeaweedFS
RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev libcurl4-openssl-dev libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql pgsql gd curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

COPY src/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html
