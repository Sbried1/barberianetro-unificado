
FROM php:8.3-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

# Apache sirve solo la carpeta public/. config/, vendor/, libs/, scripts/,
# los .sql y el .env quedan fuera del DocumentRoot.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

# Bloquear archivos sensibles por HTTP (por si alguno quedara dentro de public/)
RUN printf '%s\n' \
    '<FilesMatch "(?i)\.(sql|txt|md|log|bak|yml|yaml|env|ini|conf|sh|json|lock)$">' \
    '    Require all denied' \
    '</FilesMatch>' \
    '<FilesMatch "^(\..*|Dockerfile)$">' \
    '    Require all denied' \
    '</FilesMatch>' \
    '<DirectoryMatch "/\.git">' \
    '    Require all denied' \
    '</DirectoryMatch>' \
    '<Directory /var/www/html>' \
    '    Options -Indexes' \
    '</Directory>' \
    > /etc/apache2/conf-available/seguridad.conf \
 && a2enconf seguridad

COPY . /var/www/html/

EXPOSE 80
