FROM php:8.1-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli

# Copy project files to Apache document root
COPY public_html/ /var/www/html/

# Set recommended permissions
RUN chown -R www-data:www-data /var/www/html

# Create a startup script to handle environment variables
RUN echo '#!/bin/bash\n\
# Set environment variables for PHP\n\
if [ ! -z "$MYSQL_HOST" ]; then\n\
    echo "MYSQL_HOST=$MYSQL_HOST" >> /var/www/html/.env\n\
fi\n\
if [ ! -z "$MYSQL_PORT" ]; then\n\
    echo "MYSQL_PORT=$MYSQL_PORT" >> /var/www/html/.env\n\
fi\n\
if [ ! -z "$MYSQL_USER" ]; then\n\
    echo "MYSQL_USER=$MYSQL_USER" >> /var/www/html/.env\n\
fi\n\
if [ ! -z "$MYSQL_PASSWORD" ]; then\n\
    echo "MYSQL_PASSWORD=$MYSQL_PASSWORD" >> /var/www/html/.env\n\
fi\n\
if [ ! -z "$MYSQL_DATABASE" ]; then\n\
    echo "MYSQL_DATABASE=$MYSQL_DATABASE" >> /var/www/html/.env\n\
fi\n\
if [ ! -z "$BASE_URL" ]; then\n\
    echo "BASE_URL=$BASE_URL" >> /var/www/html/.env\n\
fi\n\
if [ ! -z "$ADMIN_BASE_URL" ]; then\n\
    echo "ADMIN_BASE_URL=$ADMIN_BASE_URL" >> /var/www/html/.env\n\
fi\n\
\n\
# Start Apache\n\
apache2-foreground' > /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh

# Expose port 80
EXPOSE 80

# Use the startup script
CMD ["/usr/local/bin/start.sh"] 