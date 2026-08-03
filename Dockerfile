FROM php:8.5-alpine
LABEL org.opencontainers.image.authors="Julian Xhokaxhiu <https://julianxhokaxhiu.com>"

# internal variables
ENV HTML_DIR=/var/www/html
ENV FULL_BUILDS_DIR=$HTML_DIR/builds/full

# set the working directory
WORKDIR $HTML_DIR

# install the PHP extensions we need
RUN apk add --no-cache git libzip zip \
        && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libzip-dev \
        && docker-php-ext-install zip \
        && pecl install apcu \
        && docker-php-ext-enable apcu \
        && apk del .build-deps

# set recommended settings for APCu
# see http://php.net/manual/en/apcu.configuration.php
RUN { \
    echo 'apc.ttl=7200'; \
  } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# install composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# add all the project files
COPY . $HTML_DIR

# install dependencies
RUN composer install --no-plugins --no-scripts

# fix permissions
RUN chmod -R 0775 /var/www/html \
    && chown -R www-data:www-data /var/www/html

# create volumes
VOLUME /var/www/html/builds

EXPOSE 80

CMD ["php", "-S", "0.0.0.0:80", "-t", "/var/www/html", "/var/www/html/docker/router.php"]
