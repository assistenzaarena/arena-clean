# Usiamo PHP 8.2 con Apache perché è stabile e pronto all'uso
FROM php:8.2-apache

# Installiamo estensioni minime (PDO + MySQL) che useremo nella Fase 2
RUN docker-php-ext-install pdo pdo_mysql

# Abilitiamo mod_rewrite (ci servirà dopo; non influisce ora)
RUN a2enmod rewrite

# Cartella pubblica servita da Apache
WORKDIR /var/www/html

# Copiamo SOLO la cartella public (per testare subito il server)
COPY public/ /var/www/html/

# Avviamo Apache in foreground (Richiesto nei container)
CMD ["apache2-foreground"]
