# Production Dockerfile for Grocery Billing Management System
FROM php:8.2-apache

# Install required system packages and PHP MySQL PDO extension
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure Apache DocumentRoot and Directory permissions
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy project files into container
COPY . /var/www/html/

# Set file permissions for web server
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose web port
EXPOSE 80

# Start Apache server in foreground
CMD ["apache2-foreground"]
