FROM php:7.4-cli AS build
RUN apt-get update && apt-get install -y unzip
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/ip-analyzer-src
COPY Core /var/www/ip-analyzer-src/Core
COPY tools /var/www/ip-analyzer-src/tools
COPY box.json /var/www/ip-analyzer-src/box.json
COPY composer.json /var/www/ip-analyzer-src/composer.json
COPY composer.lock /var/www/ip-analyzer-src/composer.lock
COPY start-analyzer.php /var/www/ip-analyzer-src/start-analyzer.php
RUN composer install && composer build

FROM php:7.4-cli AS run
RUN docker-php-ext-configure pcntl --enable-pcntl && docker-php-ext-install pcntl
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
ARG LICENSE_KEY
ARG ACCOUNT_ID
COPY --from=build /var/www/ip-analyzer-src/dist/ip-analyzer.phar /var/www/ip-analyzer/ip-analyzer.phar
COPY .env.example /var/www/ip-analyzer/.env
COPY ./mmdb /var/www/ip-analyzer/mmdb
COPY ./blacklist /var/www/ip-analyzer/blacklist
WORKDIR /var/geoip
RUN apt-get update && apt-get install -y wget gzip libsodium-dev vim
RUN wget https://github.com/maxmind/geoipupdate/releases/download/v4.9.0/geoipupdate_4.9.0_linux_amd64.deb
RUN dpkg -i geoipupdate_4.9.0_linux_amd64.deb
RUN docker-php-ext-install sodium
RUN sed -i "s/AccountID.*/AccountID ${ACCOUNT_ID}/g" /etc/GeoIP.conf && sed -i "s/LicenseKey.*/LicenseKey ${LICENSE_KEY}/g" /etc/GeoIP.conf && sed -i "s/EditionIDs.*/EditionIDs GeoLite2-ASN GeoLite2-City GeoLite2-Country/g" /etc/GeoIP.conf
RUN echo "0 0 * * * /usr/bin/geoipupdate -v" >> /etc/crontab
RUN /usr/bin/geoipupdate -v
RUN chown root:root /var/www/ip-analyzer/.env && chmod 600 /var/www/ip-analyzer/.env
WORKDIR /var/www/ip-analyzer
CMD ["/usr/local/bin/php", "ip-analyzer.phar", "start", "--env", "/var/www/ip-analyzer/.env"]