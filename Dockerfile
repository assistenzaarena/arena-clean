FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql
RUN a2enmod rewrite
RUN mkdir -p /var/www/html/uploads/prizes && chmod -R 777 /var/www/html/uploads/prizes

WORKDIR /var/www/html

COPY public/ /var/www/html/
COPY src/ /var/www/html/src/
COPY sql/ /var/www/html/sql/

CMD ["apache2-foreground"]
