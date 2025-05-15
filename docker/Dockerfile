FROM php:8.2-apache

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    NODE_VERSION=18.17.0 \
    NVM_DIR=/root/.nvm \
    PATH="/root/.nvm/versions/node/v18.17.0/bin/:$PATH"

# Update and install dependencies
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
        mariadb-client \
        ca-certificates \
        gnupg \
        lsb-release && \
        docker-php-ext-configure gd --with-freetype --with-jpeg && \
        docker-php-ext-install -j$(nproc) \
        gd \
        mysqli \
        pdo \
        pdo_mysql && \
        apt-get clean && \
        rm -rf /var/lib/apt/lists/*

# Composer installation
RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

# Install PECL extensions conditionally
RUN pecl install zip xdebug && \
    docker-php-ext-enable zip xdebug

# Apache config
COPY ./docker/default.conf /etc/apache2/sites-available/deepdevs.test.conf
RUN a2ensite deepdevs.test

# PHP config
COPY ./docker/docker-php-ext-xdebug3-ext.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug-ext.ini
COPY ./docker/docker-php-extra.ini /usr/local/etc/php/conf.d/docker-php-extra.ini

# Utilities
COPY ./docker/bash /root/bash
COPY ./docker/firstRun.sh /root/firstRun.sh
# RUN bash /root/firstRun.sh

# Install Node via NVM
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash && \
    . "$NVM_DIR/nvm.sh" && \
    nvm install $NODE_VERSION && \
    nvm use $NODE_VERSION && \
    nvm alias default $NODE_VERSION && \
    npm install -g npm@10.2.1

# Confirm versions
RUN node -v && npm -v
