# Docker image providing Magento 1.9 running on PHP 5.5.
# Adapted from https://github.com/occitech/docker/blob/master/magento/php5.5/apache/Dockerfile
FROM php:5.5-apache

RUN requirements="libpng12-dev libmcrypt-dev libmcrypt4 libcurl3-dev libfreetype6 libjpeg62-turbo libpng12-dev libfreetype6-dev libjpeg62-turbo-dev libxml2-dev mysql-client-5.5" \
    && apt-get update && apt-get install -y $requirements && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install gd \
    && docker-php-ext-install mcrypt \
    && docker-php-ext-install mbstring \
    && docker-php-ext-install soap \
    && docker-php-ext-install zip \
    && requirementsToRemove="libpng12-dev libmcrypt-dev libcurl3-dev libpng12-dev libfreetype6-dev libjpeg62-turbo-dev" \
    && apt-get purge --auto-remove -y $requirementsToRemove

# Install Node v4 with latest NPM
RUN curl -sL https://deb.nodesource.com/setup_4.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g npm

RUN usermod -u 1000 www-data
RUN a2enmod rewrite
RUN sed -i -e 's/\/var\/www\/html/\/var\/www\/htdocs/' /etc/apache2/apache2.conf

# Tweak PHP to fit Magento a little better.
COPY docker/v1.9-php5.5/etc/php/conf.d/*.ini /usr/local/etc/php/conf.d/

# Install n98-magerun
ENV N98_MAGERUN_URL http://files.magerun.net/n98-magerun-latest.phar
RUN curl -o /usr/local/bin/n98-magerun $N98_MAGERUN_URL \
    && chmod +x /usr/local/bin/n98-magerun

# Bring in .n98-magerun.yaml with new defaults for n98-magerun
# (See https://github.com/netz98/n98-magerun#overwrite-default-settings)
COPY docker/.n98-magerun.yaml /root

# Install Modman for working with our extension
ENV MODMAN_URL https://raw.githubusercontent.com/colinmollenhour/modman/master/modman
RUN curl -Lo /usr/local/bin/modman $MODMAN_URL && chmod +x /usr/local/bin/modman

# Install composer
RUN curl -Lo /usr/local/bin/composer https://getcomposer.org/composer.phar && \
    chmod +x /usr/local/bin/composer

####################################################################################################
#                        ^^^ TODO: All of the above can go into a new base image ^^^
####################################################################################################

####################################################################################################
# Install Magento files locally
# We can't run the DB installation as part of the Docker build process because it involves a linked
# container. So instead we *download* the Magento source files and extract them.
ENV MAGENTO_VERSION 1.9.2.2
ENV MAGENTO_VERSION_BY_NAME magento-mirror-$MAGENTO_VERSION
ENV MAGENTO_HOSTNAME magento
ENV MAGENTO_PORT 1955
ENV MAGENTO_BASE_URL http://${MAGENTO_HOSTNAME}:${MAGENTO_PORT}

RUN n98-magerun install \
    --magentoVersionByName=$MAGENTO_VERSION_BY_NAME \
    --installationFolder=/var/www/htdocs \
    --only-download

# Ensure we serve Magento on the same port internally & externally (i.e., we want
# http://magento:1955 to work both inside the Docker container and outside.
RUN sed -i -e "s/Listen 80/Listen $MAGENTO_PORT/" /etc/apache2/apache2.conf
EXPOSE $MAGENTO_PORT

####################################################################################################
# Copy sample data into image
# Ideally we'd let n98-magerun do this, but there's not currently a way to download sample data
# separate from installing it. Additionally, Magento requires sample data to be in place *before*
# running the installer. So our compromise is we download sample data during the build process,
# then the install-magento script picks it up and loads it before finishing the install process.
RUN mkdir $HOME/_magento_sample_data

# Help avoid long sample data download times during build by favoring locally cached data
COPY docker/.magento_sample_data/* /root/_magento_sample_data/

# ENV MAGENTO_SAMPLE_DATA_URL https://sourceforge.net/projects/mageloads/files/assets/1.9.1.0/magento-sample-data-1.9.1.0.tar.gz
ENV MAGENTO_SAMPLE_DATA_URL https://s3-us-west-2.amazonaws.com/pricewaiter-magento-sample-data/magento-sample-data-1.9.1.0.tar.gz
ENV MAGENTO_SAMPLE_DATA_FILE /root/_magento_sample_data/magento-sample-data-1.9.1.0.tar.gz

RUN test -f $MAGENTO_SAMPLE_DATA_FILE || curl -Lo $MAGENTO_SAMPLE_DATA_FILE $MAGENTO_SAMPLE_DATA_URL

# Bring in our custom scripts
# TODO: Move above Magento downloads once things settle down.
COPY docker/v1.9-php5.5/bin/* /usr/local/bin/

# Enable displaying exception information on screen
RUN mv /var/www/htdocs/errors/local.xml.sample /var/www/htdocs/errors/local.xml

####################################################################################################
# Set up PriceWaiter extension inside Docker container
ENV PRICEWAITER_API_KEY MAGENTO
ENV PRICEWAITER_SHARED_SECRET SECRET
ENV MODMAN_DIR=/var/www/htdocs/.modman
ENV PRICEWAITER_DIR=$MODMAN_DIR/pricewaiter

RUN mkdir -p MODMAN_DIR && chown -R www-data:www-data MODMAN_DIR && \
    mkdir -p PRICEWAITER_DIR && chown -R www-data:www-data PRICEWAITER_DIR

# Do composer install first to avoid having to re-do it every time a source file changes

WORKDIR $PRICEWAITER_DIR

COPY composer.json composer.lock ./
RUN composer install

# Finally, copy all other files into the image...
COPY . ./

# And get our extension linked in correctly
WORKDIR /var/www/htdocs

RUN modman deploy pricewaiter

CMD ["install-magento-then", "apache2-foreground"]
