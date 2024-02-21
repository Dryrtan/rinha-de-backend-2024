FROM php:8-fpm
RUN docker-php-ext-install opcache
COPY php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY ./src/ /var/www/html/