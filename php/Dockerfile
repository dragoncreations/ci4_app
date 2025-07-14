FROM php:fpm

RUN apt-get update && apt-get install -y \
    libicu-dev \
    zip unzip git \
    && docker-php-ext-install intl