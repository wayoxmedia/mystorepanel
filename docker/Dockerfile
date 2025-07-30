FROM php:8.2-apache

# Environment setup
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    NODE_VERSION=20.14.0 \
    NVM_DIR=/root/.nvm \
    PATH="/root/.nvm/versions/node/v$NODE_VERSION/bin/:$PATH"

# Update and install system dependencies
RUN apt-get update && apt-get upgrade -y && \
    apt-get install -y --no-install-recommends \
        curl \
        git \
        nano \
        zip \
        unzip \
        libzip-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libxml2-utils \
        ca-certificates \
        gnupg \
        lsb-release && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
        gd \
        pdo \
        pdo_mysql && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install PECL extensions
RUN pecl install zip xdebug && \
    docker-php-ext-enable zip xdebug

# Apache virtual host config
COPY ./docker/default.conf /etc/apache2/sites-available/mystorepanel.test.conf
RUN a2ensite mystorepanel.test && a2enmod rewrite

# PHP configuration
COPY ./docker/docker-php-ext-xdebug3-ext.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug-ext.ini
COPY ./docker/docker-php-extra.ini /usr/local/etc/php/conf.d/docker-php-extra.ini

# Utility scripts
COPY ./docker/bash /root/bash
COPY ./docker/firstRun.sh /root/firstRun.sh
# Optional: RUN bash /root/firstRun.sh

# Install Node.js via NVM
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash && \
    . "$NVM_DIR/nvm.sh" && \
    nvm install $NODE_VERSION && \
    nvm use $NODE_VERSION && \
    nvm alias default $NODE_VERSION && \
    npm install -g npm@10.7.0 && \
    node -v && npm -v
