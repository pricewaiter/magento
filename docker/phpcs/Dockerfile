FROM php:5.6-cli

RUN apt-get update && \
    apt-get install -y \
        git \
        zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install zip

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php && \
    php -r "unlink('composer-setup.php');" && \
    mv composer.phar /usr/local/bin/composer && \
    chmod +x /usr/local/bin/composer

RUN mkdir -p /usr/src/ext/src
WORKDIR /usr/src/ext

RUN git clone https://github.com/magento/marketplace-eqp.git && \
    cd marketplace-eqp && \
    composer install && \
    cd ..

# Set installed_paths, per README
ENV PHPCS_CMD="php marketplace-eqp/vendor/squizlabs/php_codesniffer/scripts/phpcs"
RUN $PHPCS_CMD --config-set installed_paths /usr/src/ext/marketplace-eqp

COPY docker/phpcs/bin/run-phpcs /usr/local/bin/

COPY app src/app
COPY js src/js
COPY skin src/skin

CMD [ "run-phpcs" ]

