# SB-Tech OMS — dev container (Apache + PHP with the extensions the app needs).
# Built by docker-compose.yml; the repo is bind-mounted at /var/www/html.
FROM php:8.4-apache

# mysqli is the app's DB layer (classes/Database.php); gd/zip/intl back
# dompdf + phpword document generation.
RUN docker-php-ext-install mysqli gd zip intl \
    && a2enmod rewrite headers

# AllowOverride so the committed .htaccess router works (RULES DEPLOY-02).
RUN { \
      echo '<Directory /var/www/html/>'; \
      echo '    AllowOverride All'; \
      echo '    Require all granted'; \
      echo '</Directory>'; \
    } > /etc/apache2/conf-available/sbtech-override.conf \
    && a2enconf sbtech-override

COPY docker/apache.conf /etc/apache2/conf-available/sbtech.conf
RUN a2enconf sbtech
