# makemyki.de – PHP 8.3 mit Apache (Apache, weil Weiterleitungen, 404-Seite und Sperren in .htaccess-Dateien stehen)
FROM php:8.3-apache

RUN a2enmod rewrite headers deflate \
 && { \
      echo 'ServerTokens Prod'; \
      echo 'ServerSignature Off'; \
      echo '<Directory /var/www/html>'; \
      echo '  AllowOverride All'; \
      echo '</Directory>'; \
      echo '# Laufzeitdaten und Zugangsdaten serverseitig sperren.'; \
      echo '# Greift auch dann, wenn ein Volume die .htaccess in den Datenordnern überdeckt.'; \
      echo '<DirectoryMatch "^/var/www/html/(api|statistik)/daten">'; \
      echo '  Require all denied'; \
      echo '</DirectoryMatch>'; \
      echo '<FilesMatch "^\.env">'; \
      echo '  Require all denied'; \
      echo '</FilesMatch>'; \
    } > /etc/apache2/conf-available/makemyki.conf \
 && a2enconf makemyki \
 && { echo 'expose_php = Off'; echo 'date.timezone = Europe/Berlin'; } > /usr/local/etc/php/conf.d/makemyki.ini

COPY . /var/www/html/

RUN sed -i 's/\r$//' /var/www/html/docker-entrypoint.sh \
 && mv /var/www/html/docker-entrypoint.sh /usr/local/bin/makemyki-entrypoint \
 && chmod +x /usr/local/bin/makemyki-entrypoint \
 && mkdir -p /var/www/html/api/daten /var/www/html/statistik/daten \
 && chown -R www-data:www-data /var/www/html/api/daten /var/www/html/statistik/daten

EXPOSE 80
ENTRYPOINT ["makemyki-entrypoint"]
CMD ["apache2-foreground"]
