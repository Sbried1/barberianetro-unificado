
FROM php:8.3-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

# Bloquear archivos sensibles por HTTP
RUN printf '%s\n' \
    '<FilesMatch "(?i)\.(sql|txt|md|log|bak|yml|yaml|env|ini|conf|sh)$">' \
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
