FROM dunglas/frankenphp:php8.4-bookworm

# Installation des extensions PHP
RUN install-php-extensions \
    ftp \
    bz2 \
    pdo_mysql \
    zip \
    gd \
    imagick \
    opcache \
    redis \
    pcntl \
    bcmath \
    intl \
    excimer

RUN apt-get update

# Install MariaDB Client
RUN apt-get install -y mariadb-client

# Install Composer
RUN curl --silent --show-error https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Nodejs and NPM
RUN curl -fsSL https://deb.nodesource.com/setup_22.x -o nodesource_setup.sh && \
    bash nodesource_setup.sh && \
    apt-get install -y nodejs

# Install supervisor
RUN apt-get install -y supervisor
COPY docker-init/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Install crontab
RUN apt-get install -y cron

# Configure cron for Laravel
RUN echo "* * * * * root php /app/artisan schedule:run >> /var/log/cron.log 2>&1" >> /etc/crontab
RUN touch /var/log/cron.log

# Setting PHP Configuration
COPY docker-init/php.ini $PHP_INI_DIR/php.ini

# Setting Imagick Configuration
COPY docker-init/imagick-policy.xml /etc/ImageMagick-6/policy.xml

# Setting Caddy configuration
COPY docker-init/production.caddyfile /etc/caddy/Caddyfile.d/production.caddyfile

WORKDIR /app

COPY composer.json composer.lock /app/
RUN composer install --no-scripts --no-autoloader

COPY package.json package-lock.json /app/
RUN npm install

COPY . /app/
COPY stack.env /app/.env

# Create directories
RUN mkdir -p /app/storage/framework/{sessions,views,cache}
RUN chmod -R 775 /app/storage/framework

RUN composer dump-autoload --optimize
RUN php artisan ziggy:generate
RUN php artisan storage:link
RUN npm run build:ssr

# Copy entrypoint script
COPY docker-init/entrypoint-production.sh /app/docker-init/entrypoint.sh
RUN chmod +x /app/docker-init/entrypoint.sh

ENTRYPOINT ["/app/docker-init/entrypoint.sh"]
