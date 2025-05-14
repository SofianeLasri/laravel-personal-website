FROM dunglas/frankenphp

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
    bcmath

# Installation de Composer
RUN curl --silent --show-error https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Installation de Nodejs et NPM
RUN curl -fsSL https://deb.nodesource.com/setup_22.x -o nodesource_setup.sh && \
    bash nodesource_setup.sh && \
    apt-get install -y nodejs

# Installation de supervisor
RUN apt-get install -y supervisor
COPY docker-init/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Configuration PHP
COPY docker-init/php.ini $PHP_INI_DIR/php.ini

# Création du répertoire de travail
WORKDIR /app

COPY composer.json composer.lock /app/
RUN composer install --no-scripts --no-autoloader

COPY package.json package-lock.json /app/
RUN npm install

COPY . /app/

# Création des dossiers de cache
RUN mkdir -p /app/storage/framework/{sessions,views,cache}
RUN chmod -R 775 /app/storage/framework

# Finalisation de l'installation
RUN composer dump-autoload --optimize
RUN php artisan ziggy:generate
RUN php artisan storage:link
RUN npm run build:ssr

# Copie du script d'entrypoint
COPY docker-init/entrypoint-production.sh /app/docker-init/entrypoint.sh
RUN chmod +x /app/docker-init/entrypoint.sh

# Entrypoint
RUN php artisan key:generate
RUN php artisan migrate --force

RUN service supervisor start
RUN supervisorctl start all

RUN php artisan inertia:start-ssr &

ENTRYPOINT ["php", "artisan", "octane:frankenphp"]
