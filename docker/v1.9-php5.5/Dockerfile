FROM pricewaiter/docker-magento:v19php55

ENV MAGENTO_HOSTNAME magento
ENV MAGENTO_PORT 1955
ENV MAGENTO_BASE_URL http://${MAGENTO_HOSTNAME}:${MAGENTO_PORT}

# Ensure we serve Magento on the same port internally & externally (i.e., we want
# http://magento:1955 to work both inside the Docker container and outside.
RUN sed -i -e "s/Listen 80/Listen $MAGENTO_PORT/" /etc/apache2/apache2.conf
EXPOSE $MAGENTO_PORT

# Bring in our custom scripts
COPY docker/v1.9-php5.5/bin/* /usr/local/bin/

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

CMD [ "install-magento-then", "configure-pricewaiter-then", "apache2-foreground" ]
