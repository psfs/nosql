FROM php:7.2-fpm

MAINTAINER "Fran López"

RUN apt-get update && apt-get install -y pkg-config libssl-dev libsasl2-dev libmongoc-1.0-0
RUN docker-php-ext-install gettext
RUN pecl install redis mongodb \
    && docker-php-ext-enable redis \
    && docker-php-ext-enable mongodb
