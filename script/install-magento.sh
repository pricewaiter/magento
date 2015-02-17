#!/usr/bin/env bash
set -ex

# Create database for test store
mysql -e 'create database teststore;'

# Configure php and n98-magerun
phpenv config-add "$TRAVIS_BUILD_DIR/travis/travis.php.ini"
cp "$TRAVIS_BUILD_DIR/travis/.n98-magerun.yml" "$HOME/."

# Create install directory
mkdir -p "$HOME/build"
cd "$HOME/build"

# Install magento
mage install \
    --baseUrl="http://$VIRTUALHOST_NAME" \
    --dbHost="localhost" \
    --dbName="teststore" \
    --dbPass="" \
    --dbUser="root" \
    --installationFolder=. \
    --installSampleData=yes \
    --magentoVersionByName="magento-ce-1.9.0.1" \
    --useDefaultConfigParams=yes
