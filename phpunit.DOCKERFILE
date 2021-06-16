FROM php:7.2

RUN pecl install --force mongodb
RUN echo "extension=mongodb.so" > /usr/local/etc/php/conf.d/mongodb.ini
COPY --from=composer:1.10 /usr/bin/composer /usr/bin/composer
