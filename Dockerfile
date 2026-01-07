FROM dunglas/frankenphp:php8.4-bookworm

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
    xdebug

RUN apt-get update

# Install MariaDB Client
RUN apt-get install -y mariadb-client

# Install Composer
RUN curl --silent --show-error https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Bun
RUN apt-get install -y unzip
RUN curl -fsSL https://bun.sh/install | bash && \
    ln -s /root/.bun/bin/bun /usr/local/bin/bun

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
