FROM php:8.3-apache

# Installer les extensions MySQL nécessaires
RUN docker-php-ext-install mysqli pdo_mysql
RUN printf 'upload_max_filesize=50M\npost_max_size=512M\nmax_file_uploads=1000\n' > /usr/local/etc/php/conf.d/prefa-uploads.ini
RUN printf '<Directory /var/www/html/uploads>\nRequire all denied\n</Directory>\n' > /etc/apache2/conf-available/prefa-uploads.conf && a2enconf prefa-uploads

# Copier l'application web
COPY ./src /var/www/html/

# Copier les scripts hors du dossier web
COPY ./scripts /scripts/

# Donner les bons droits
RUN chown -R www-data:www-data /var/www/html

COPY ./scripts/app-entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint
ENTRYPOINT ["/usr/local/bin/app-entrypoint"]

CMD ["apache2-foreground"]
