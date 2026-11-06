# Render web service: PHP 8.2 + Apache, with Python for the Excel / ranking scripts
FROM php:8.2-apache

# mysqli for the database; Python in its own virtualenv for rankings/*.py
RUN apt-get update \
    && apt-get install -y --no-install-recommends python3 python3-venv \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install mysqli \
    && python3 -m venv /opt/venv \
    && /opt/venv/bin/pip install --no-cache-dir openpyxl pdfplumber requests xlrd

ENV PYTHON_PATH=/opt/venv/bin/python

# Same PHP limits as XAMPP (uploads up to 40 MB)
RUN { \
        echo 'upload_max_filesize = 40M'; \
        echo 'post_max_size = 40M'; \
        echo 'memory_limit = 256M'; \
        echo 'max_execution_time = 120'; \
    } > /usr/local/etc/php/conf.d/tournament.ini

# Render sends traffic to $PORT (10000 by default); there's no index.php, so start at the login page
ENV PORT=10000
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
    && echo 'DirectoryIndex login.php index.php' > /etc/apache2/conf-enabled/tournament.conf

COPY . /var/www/html/

# Apache writes uploaded pictures here
RUN mkdir -p /var/www/html/uploads/tournaments /var/www/html/uploads/admins \
    && chown -R www-data:www-data /var/www/html/uploads
