# ==============================================================================
# SECURE360 - PRODUCTION DOCKERFILE
# PHP 8.2 + Apache with MySQL (PDO & mysqli), rewrite, headers, and front controller
# ==============================================================================

FROM php:8.2-apache

ENV TZ=Asia/Kolkata

# 1. Install system dependencies & PHP MySQL extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    unzip \
    tzdata \
    && ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone \
    && docker-php-ext-install -j$(nproc) \
        mysqli \
        pdo \
        pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 2. Configure Apache MPM (mod_php requires mpm_prefork), suppress ServerName warning, and enable modules
RUN rm -f /etc/apache2/mods-enabled/mpm_event.* \
          /etc/apache2/mods-enabled/mpm_worker.* \
    && a2enmod mpm_prefork rewrite headers remoteip \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# 3. Use production PHP configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Apply recommended security and production PHP directives
RUN { \
    echo "expose_php = Off"; \
    echo "memory_limit = 256M"; \
    echo "upload_max_filesize = 20M"; \
    echo "post_max_size = 25M"; \
    echo "max_execution_time = 60"; \
    echo "date.timezone = Asia/Kolkata"; \
    echo "session.cookie_httponly = 1"; \
    echo "session.use_strict_mode = 1"; \
    echo "log_errors = On"; \
    echo "error_log = /dev/stderr"; \
} > "$PHP_INI_DIR/conf.d/secure360-production.ini"

# 4. Explicitly configure Apache DocumentRoot and Directory permissions for /var/www/html/public
RUN { \
    echo '<VirtualHost *:80>'; \
    echo '    ServerAdmin webmaster@localhost'; \
    echo '    DocumentRoot /var/www/html/public'; \
    echo ''; \
    echo '    <Directory /var/www/html/public>'; \
    echo '        Options -MultiViews -Indexes +FollowSymLinks'; \
    echo '        AllowOverride All'; \
    echo '        Require all granted'; \
    echo '    </Directory>'; \
    echo ''; \
    echo '    ErrorLog ${APACHE_LOG_DIR}/error.log'; \
    echo '    CustomLog ${APACHE_LOG_DIR}/access.log combined'; \
    echo '</VirtualHost>'; \
} > /etc/apache2/sites-available/000-default.conf

# Validate Apache configuration at build time (fails the build if any syntax error or multiple MPMs exist)
RUN apache2ctl configtest

# 5. Set working directory and copy application source code
WORKDIR /var/www/html

COPY . /var/www/html

# 6. Ensure required writable directories exist and set strict permissions
# Codebase remains read-only to Apache; only storage and uploads are writable
RUN mkdir -p /var/www/html/storage/cache \
             /var/www/html/storage/logs \
             /var/www/html/storage/uploads \
             /var/www/html/public/uploads \
             /var/www/html/public/uploads/selfies \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/public/uploads \
    && chmod -R 775 /var/www/html/storage /var/www/html/public/uploads

# 7. Setup entrypoint script for dynamic Railway port handling
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

# 8. Expose default HTTP port (dynamic PORT supported by entrypoint)
ENV PORT=80
EXPOSE 80

# 9. Set entrypoint and CMD (Apache starts directly as PID 1)
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
