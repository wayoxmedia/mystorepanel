FROM php:8.2-apache

ENV COMPOSER_ALLOW_SUPERUSER=1

#RUN docker-html-ext-install mysqli && docker-html-ext-enable mysqli
RUN apt-get update && apt-get upgrade -y && apt-get install -qy curl \
			git \
			nano \
			libzip-dev \
			mariadb-client \
			zip \
			unzip

RUN apt-get update && apt-get install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

# COMPOSER
RUN php -r "readfile('https://getcomposer.org/installer');" | php -- --install-dir=/usr/local/bin --filename=composer

RUN docker-php-ext-install mysqli \
	pdo \
	pdo_mysql

# RUN pecl install zip && docker-php-ext-install zip
RUN bash -c '[[ -n "$(pecl list | grep zip)" ]]\
 || (pecl install zip && docker-php-ext-install zip)'

# RUN pecl install xdebug && docker-php-ext-enable xdebug
RUN bash -c '[[ -n "$(pecl list | grep xdebug)" ]]\
 || (pecl install xdebug && docker-php-ext-enable xdebug)'


COPY ./docker/docker-php-ext-xdebug3-ext.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug-ext.ini
COPY ./docker/docker-php-extra.ini /usr/local/etc/php/conf.d/docker-php-extra.ini
#COPY ./docker/default.conf /etc/apache2/sites-enabled/eglee.test.conf
COPY ./docker/default.conf /etc/apache2/sites-available/deepdevs.test.conf
RUN a2ensite deepdevs.test

### UTILITIES
#COPY ./docker/git-prompt.sh /root/.git-prompt.sh
COPY ./docker/bash /root/bash
COPY ./docker/firstRun.sh /root/firstRun.sh
#RUN bash /root/firstRun.sh


### NODE.JS
ENV NODE_VERSION=18.17.0
RUN apt install -y curl
RUN curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
ENV NVM_DIR=/root/.nvm
RUN . "$NVM_DIR/nvm.sh" && nvm install ${NODE_VERSION}
RUN . "$NVM_DIR/nvm.sh" && nvm use v${NODE_VERSION}
RUN . "$NVM_DIR/nvm.sh" && nvm alias default v${NODE_VERSION}
ENV PATH="/root/.nvm/versions/node/v${NODE_VERSION}/bin/:${PATH}"
RUN npm install -g npm@10.2.1
RUN node --version
RUN npm --version

