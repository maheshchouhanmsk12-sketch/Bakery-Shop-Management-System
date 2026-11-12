#!/usr/bin/env bash
set -e

# Default port assigned dynamically by Render (defaults to 10000)
PORT="${PORT:-10000}"

echo "==> Configuring Apache for Render Environment..."
echo "==> Listening on container port: ${PORT}"

# Update Apache port listening configuration
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Render Reverse Proxy Fix: Prevent Apache internal port (e.g. 10000) from leaking in redirects
if ! grep -q "ServerName localhost" /etc/apache2/apache2.conf; then
    echo "ServerName localhost" >> /etc/apache2/apache2.conf
fi

if ! grep -q "UseCanonicalName Off" /etc/apache2/apache2.conf; then
    echo "UseCanonicalName Off" >> /etc/apache2/apache2.conf
fi

if ! grep -q "UseCanonicalPhysicalPort Off" /etc/apache2/apache2.conf; then
    echo "UseCanonicalPhysicalPort Off" >> /etc/apache2/apache2.conf
fi

# Enable AllowOverride All so .htaccess rules are respected
sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

echo "==> Apache configuration updated successfully. Starting web server..."
exec apache2-foreground
