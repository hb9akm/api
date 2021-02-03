FROM php:8-apache
RUN a2enmod rewrite
COPY --chown=www-data:www-data src /var/www/html/
