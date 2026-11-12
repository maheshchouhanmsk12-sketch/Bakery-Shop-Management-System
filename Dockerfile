FROM php:8.2-apache

# Install required system packages and PHP extension build dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    ca-certificates \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) mysqli pdo_mysql gd zip \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Optimize PHP runtime configurations
RUN echo "file_uploads = On\n\
memory_limit = 256M\n\
upload_max_filesize = 64M\n\
post_max_size = 64M\n\
max_execution_time = 300\n\
date.timezone = UTC\n\
display_errors = Off\n\
log_errors = On" > /usr/local/etc/php/conf.d/custom.ini

# Set web root directory
WORKDIR /var/www/html

# Copy all application files
COPY . /var/www/html/

# Set correct ownership, permissions, and ensure startup script is executable
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod +x /var/www/html/start.sh

# Expose Render standard container port
EXPOSE 10000

# Execute custom startup script
CMD ["/bin/bash", "/var/www/html/start.sh"]

