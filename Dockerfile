FROM dunglas/frankenphp

ARG INSTALL_XDEBUG=false
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
    $( [ "$INSTALL_XDEBUG" = "true" ] && echo "xdebug" )

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

# Setting PHP Configuration
COPY docker-init/php.ini $PHP_INI_DIR/php.ini