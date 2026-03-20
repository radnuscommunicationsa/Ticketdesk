FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip

RUN docker-php-ext-install pdo pdo_mysql mysqli

COPY . /var/www/html/