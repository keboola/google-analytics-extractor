FROM php:7-cli

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_PROCESS_TIMEOUT 3600

# Deps
RUN apt-get update
RUN apt-get install -y wget curl make git bzip2 time libzip-dev zip unzip openssl vim

# PHP
RUN docker-php-ext-install sockets

# Composer
WORKDIR /root
RUN cd \
  && curl -sS https://getcomposer.org/installer | php \
  && ln -s /root/composer.phar /usr/local/bin/composer

# Main
ADD . /code
WORKDIR /code
RUN echo "memory_limit = -1" >> /usr/local/etc/php/php.ini
RUN echo "date.timezone = \"Europe/Prague\"" >> /usr/local/etc/php/php.ini
RUN composer selfupdate && composer install --no-interaction

CMD php ./run.php --data=/data
