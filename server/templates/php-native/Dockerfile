FROM php:8.3-apache

RUN a2enmod rewrite headers \
    && printf '%s\n' \
       'expose_php=Off' \
       'display_errors=Off' \
       'log_errors=On' \
       'memory_limit=128M' \
       'upload_max_filesize=16M' \
       'post_max_size=16M' \
       > /usr/local/etc/php/conf.d/autodeploy.ini

WORKDIR /var/www/html
COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80
