FROM php:8.3-apache

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite module (if needed)
RUN a2enmod rewrite

WORKDIR /var/www/html

# Copy application source code into the container
COPY . /var/www/html

# Adjust permissions
RUN chown -R www-data:www-data /var/www/html

# Use custom Apache config that serves the public folder
COPY apache.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80
