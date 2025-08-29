# Usiamo l'immagine ufficiale PHP 8.2 con Apache perché:
# - è stabile
# - ha già Apache configurato
# - su Railway va liscia
FROM php:8.2-apache

# Installiamo estensioni necessarie per lavorare con MySQL in modo moderno e sicuro (PDO + mysqli)
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Abilitiamo mod_rewrite di Apache per eventuali URL "pulite" in futuro (utile quando faremo routing)
RUN a2enmod rewrite

# Impostiamo la cartella di lavoro a quella standard di Apache
WORKDIR /var/www/html

# Copiamo tutto dentro il container:
# - /public verrà servita da Apache
# - /src contiene PHP condiviso (include, config, partials)
# - /sql lo usiamo per riferimento schema (non serve a runtime)
COPY public/ /var/www/html/
COPY src/ /var/www/html/src/
COPY sql/ /var/www/html/sql/

# Miglioriamo permessi: Apache deve poter leggere (e se servisse scrivere in cache/log futuri)
RUN chown -R www-data:www-data /var/www/html

# Impostiamo variabile d'ambiente per distinguere sviluppo/produzione (Railway la può sovrascrivere)
ENV APP_ENV=production

# Expose della porta 80: è quella su cui Apache ascolta
EXPOSE 80

# Comando di avvio: esegue Apache in foreground (richiesto in container)
CMD ["apache2-foreground"]
