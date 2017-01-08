FROM php:7.1-apache
MAINTAINER Julian Xhokaxhiu <info at julianxhokaxhiu dot com>

# internal variables
ENV HTML_DIR /var/www/html
ENV BUILDS_DIR $HTML_DIR/builds

# set the working directory
WORKDIR $HTML_DIR

# enable mod_rewrite
RUN a2enmod rewrite

# install latest version of composer
ADD https://getcomposer.org/composer.phar /usr/local/bin/composer
RUN chmod 0755 /usr/local/bin/composer

# add all the project files
COPY . $HTML_DIR

# fix permissions
RUN chmod -R 0775 /var/www/html

# lower down user
USER ota

# install dependencies
RUN composer install

# create volumes
VOLUME $BUILDS_DIR