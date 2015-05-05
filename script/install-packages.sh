#!/usr/bin/env bash
set -ex

sudo apt-get -qq update
sudo apt-get -qq install libxml2 libxml2-utils nginx-full nodejs npm

# Install coffe-script (have to workaround this error: https://github.com/npm/npm/issues/2245)
sudo npm config set strict-ssl false
sudo npm install -g coffee-script
