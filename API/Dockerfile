FROM php:8.2-apache

# Instalar extensiones para PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Habilitar Apache mod_rewrite (por si usas .htaccess)
RUN a2enmod rewrite
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copiar archivos del proyecto
COPY . /var/www/html/

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Instalar dependencias de Composer
RUN composer install --no-interaction --no-dev --prefer-dist

# Dar permisos a Apache
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
