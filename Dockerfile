FROM ubuntu:18.04

LABEL maintainer="Fran López<fran.lopez84@hotmail.es>"

COPY . /var/www

RUN apt-get update && apt-get install -y php7.2 php7.2-curl php7.2-gd php7.2-gmp php7.2-json \
    php7.2-intl php7.2-soap php-mongodb php-redis

WORKDIR /var/www
