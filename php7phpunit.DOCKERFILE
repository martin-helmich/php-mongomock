FROM php:7.4

RUN pecl install --force mongodb
RUN echo "extension=mongodb.so" > /usr/local/etc/php/conf.d/mongodb.ini
COPY --from=composer:2.1 /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y git zip