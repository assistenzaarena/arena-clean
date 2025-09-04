FROM php:8.2-apache

# Apache + PHP estensioni
RUN a2enmod rewrite \
 && docker-php-ext-install pdo pdo_mysql

# Dove vivrà la tua app
WORKDIR /var/www/html

# Copia sorgenti (mantieni la struttura che usi)
COPY public/ /var/www/html/
COPY src/    /var/www/html/src/
COPY sql/    /var/www/html/sql/

# Crea la dir upload DOPO le COPY e sistema owner/permessi
RUN mkdir -p /var/www/html/uploads/prizes \
 && chown -R www-data:www-data /var/www/html/uploads \
 && chmod -R 775 /var/www/html/uploads
# Se vuoi andare "grezzo" puoi usare 777 al posto di 775:
# && chmod -R 777 /var/www/html/uploads

# (Opzionale) Tweak PHP per upload più comodi
RUN { \
    echo 'upload_max_filesize=10M'; \
    echo 'post_max_size=10M'; \
} > /usr/local/etc/php/conf.d/uploads.ini

CMD ["apache2-foreground"]
