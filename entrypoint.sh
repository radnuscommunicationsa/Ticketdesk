#!/bin/bash
# Replace port 80 with Railway's PORT
PORT=${PORT:-80}
echo "Listen $PORT" > /etc/apache2/ports.conf
echo "<VirtualHost *:$PORT>
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>" > /etc/apache2/sites-available/000-default.conf

apache2-foreground