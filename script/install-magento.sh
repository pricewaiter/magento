#!/usr/bin/env bash
set -ex

# Create database for test store
mysql -e 'create database teststore;'

# Install magento
mage install \
    --baseUrl="http://magento.localdomain/" \
    --dbHost="localhost" \
    --dbName="teststore" \
    --dbPass="" \
    --dbUser="root" \
    --installationFolder=$HOME/build \
    --installSampleData=no \
    --magentoVersionByName="magento-ce-1.9.0.1" \
    --useDefaultConfigParams=yes
