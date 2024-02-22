# FROM php:8-fpm
# RUN docker-php-ext-install opcache
# COPY php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
# COPY ./api/ /var/www/html/
# RUN ls -l /var/www/html
FROM php:8-fpm
RUN docker-php-ext-install opcache
RUN apt-get update && apt-get install -y zip unzip
COPY php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY ./api/ /var/www/html/
WORKDIR /var/www/html
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install