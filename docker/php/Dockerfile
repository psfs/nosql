FROM php:7.2-fpm

MAINTAINER "Fran López"

RUN docker-php-ext-install gettext && docker-php-ext-enable gettext
RUN pecl install redis mongodb && docker-php-ext-enable redis && docker-php-ext-enable mongodb