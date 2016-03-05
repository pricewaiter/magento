#!/usr/bin/env bash
set -ex

# Create database for test store
mysql -u root -e 'create database teststore;'

# Configure n98-magerun
cp "$TRAVIS_BUILD_DIR/travis/.n98-magerun.yaml" "$HOME/"

# Create install directory
mkdir -p "$HOME/build"
cd "$HOME/build"

# Install magento
mage install \
    --baseUrl="http://$VIRTUALHOST_NAME:8008" \
    --dbHost="127.0.0.1" \
    --dbName="teststore" \
    --dbPass="" \
    --dbUser="root" \
    --installationFolder=. \
    --installSampleData=yes \
    --magentoVersionByName="magento-mirror-1.9.2.2" \
    --useDefaultConfigParams=yes

# Testing
mage dev:symlinks --on --global
mage cache:disable
mage cache:clean
mage cache:flush
mage config:set 'pricewaiter/configuration/enabled' '1'
mage config:set 'pricewaiter/configuration/api_secret' '1526ash032hag0253h'
mage config:set 'pricewaiter/configuration/api_key' 'SpsBvTB8zJIXkOuJ5GtO0IeFFpcdf6hNYGfwxfKdje5d8s5Dpk'

# Other config occurs in the virtualhost
rm "$HOME/build/.htaccess"
rm "$HOME/build/.htaccess.sample"
