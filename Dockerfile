# syntax=docker/dockerfile:1
FROM php:8.1-cli-alpine

# apt update and install essentials
RUN apk update && apk add zip

# php pcntl extension
RUN docker-php-ext-configure pcntl --enable-pcntl \
  && docker-php-ext-install pcntl \
  && docker-php-ext-enable pcntl

# php sockets extension
RUN docker-php-ext-configure sockets \
  && docker-php-ext-install sockets \
  && docker-php-ext-enable sockets

# php Redis extenions
RUN apk --no-cache add pcre-dev ${PHPIZE_DEPS} \
  && pecl install redis && docker-php-ext-enable redis \
  && apk del pcre-dev ${PHPIZE_DEPS} && rm -rf /tmp/pear

# Use the default production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# copy ip-analyzer files
COPY Core /var/www/ip-analyzer/Core
COPY vendor /var/www/ip-analyzer/vendor
COPY start-analyzer.php /var/www/ip-analyzer/start-analyzer.php
WORKDIR /var/www/ip-analyzer

CMD ["/usr/local/bin/php", "start-analyzer.php", "start"] 
