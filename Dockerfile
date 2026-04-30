FROM php:8.1-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
        libsqlite3-dev \
        libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_sqlite curl \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache AllowOverride for .htaccess support
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Set the document root to /var/www/html (crm is served under /crm)
COPY . /var/www/html/crm/

# Ensure the config directory (SQLite db) is writable by www-data
RUN mkdir -p /var/www/html/crm/config \
    && chown -R www-data:www-data /var/www/html/crm \
    && chmod -R 755 /var/www/html/crm \
    && chmod -R 775 /var/www/html/crm/config

# Store the SQLite database in a persistent volume
VOLUME ["/var/www/html/crm/config"]

EXPOSE 80
