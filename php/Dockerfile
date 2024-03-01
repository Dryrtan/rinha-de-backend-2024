FROM php:8-fpm
WORKDIR /var/www/html

COPY php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY ./api/ /var/www/html/

RUN apt-get update && apt-get install -y zip unzip
RUN docker-php-ext-install opcache
RUN apt-get install -y libpq-dev
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql
RUN docker-php-ext-install pdo pdo_pgsql pgsql
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]