FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql
RUN a2enmod rewrite

WORKDIR /var/www/html

# Copiamo i file pubblici (visibile sul web)
COPY public/ /var/www/html/

# Copiamo anche src/ e sql/ (necessari per config e DB)
COPY src/ /var/www/html/src/
COPY sql/ /var/www/html/sql/

CMD ["apache2-foreground"]
