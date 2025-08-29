FROM php:8.2-apache                         # Base PHP + Apache stabile

RUN docker-php-ext-install pdo pdo_mysql    # Estensioni DB che useremo
RUN a2enmod rewrite                         # Mod_rewrite (ci servirà dopo)

WORKDIR /var/www/html                       # Docroot di Apache

COPY public/ /var/www/html/                 # Copia i file pubblici
COPY src/ /var/www/html/src/                # *** Copia il codice PHP condiviso (config.php, ecc.)
COPY sql/ /var/www/html/sql/                # *** Copia gli SQL (non eseguiti, solo presenti)

CMD ["apache2-foreground"]                  # Avvia Apache in foreground
