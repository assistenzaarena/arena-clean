FROM php:8.2-apache

# Estensioni minime
RUN docker-php-ext-install pdo pdo_mysql

# Abilita mod_rewrite (non influisce su ping.txt ma serve dopo)
RUN a2enmod rewrite

WORKDIR /var/www/html

# Copia SOLO la cartella public (per test rapido)
COPY public/ /var/www/html/

# Avvia Apache (porta 80 -> Railway la mappa)
CMD ["apache2-foreground"]
