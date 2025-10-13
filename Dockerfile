FROM php:8.1-apache

# Install PostgreSQL extension and required packages
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copy project files to Apache document root
COPY public_html/ /var/www/html/

# Set recommended permissions
RUN chown -R www-data:www-data /var/www/html

# Create a startup script to handle environment variables
RUN echo '#!/bin/bash\n\
# Set environment variables for PHP\n\
if [ ! -z "$SUPABASE_HOST" ]; then\n\
    echo "SUPABASE_HOST=$SUPABASE_HOST" >> /var/www/html/.env\n\
fi\n\
if [ ! -z "$SUPABASE_PORT" ]; then\n\
    echo "SUPABASE_PORT=$SUPABASE_PORT" >> /var/www/html/.env\n\
fi\n\
if [ ! -z "$SUPABASE_USER" ]; then\n\
    echo "SUPABASE_USER=$SUPABASE_USER" >> /var/www/html/.env\n\
fi\n\
if [ ! -z "$SUPABASE_PASSWORD" ]; then\n\
    echo "SUPABASE_PASSWORD=$SUPABASE_PASSWORD" >> /var/www/html/.env\n\
fi\n\
if [ ! -z "$SUPABASE_DATABASE" ]; then\n\
    echo "SUPABASE_DATABASE=$SUPABASE_DATABASE" >> /var/www/html/.env\n\
fi\n\
if [ ! -z "$SUPABASE_DB_HOST" ]; then\n\
    echo "SUPABASE_DB_HOST=$SUPABASE_DB_HOST" >> /var/www/html/.env\n\
fi\n\
if [ ! -z "$SUPABASE_DB_PORT" ]; then\n\
    echo "SUPABASE_DB_PORT=$SUPABASE_DB_PORT" >> /var/www/html/.env\n\
fi\n\
if [ ! -z "$SUPABASE_DB_NAME" ]; then\n\
    echo "SUPABASE_DB_NAME=$SUPABASE_DB_NAME" >> /var/www/html/.env\n\
fi\n\
if [ ! -z "$SUPABASE_DB_USER" ]; then\n\
    echo "SUPABASE_DB_USER=$SUPABASE_DB_USER" >> /var/www/html/.env\n\
fi\n\
if [ ! -z "$SUPABASE_DB_PASSWORD" ]; then\n\
    echo "SUPABASE_DB_PASSWORD=$SUPABASE_DB_PASSWORD" >> /var/www/html/.env\n\
fi\n\
if [ ! -z "$SUPABASE_DB_SSLMODE" ]; then\n\
    echo "SUPABASE_DB_SSLMODE=$SUPABASE_DB_SSLMODE" >> /var/www/html/.env\n\
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