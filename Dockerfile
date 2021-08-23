FROM php:8-apache
RUN a2enmod rewrite
#RUN apt update && apt install -y php-mysql && apt-get autoremove -y && apt clean
RUN docker-php-ext-install pdo_mysql
COPY --chown=www-data:www-data src /var/www/html/
