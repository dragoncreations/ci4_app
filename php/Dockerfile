FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    libicu-dev \
    zip unzip git \
    && docker-php-ext-install mysqli pdo pdo_mysql intl \
    && docker-php-ext-enable mysqli

EXPOSE 9000

CMD ["php-fpm"]