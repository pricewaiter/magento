#!/usr/bin/env bash
set -ex

# Download and install n98-magerun
sudo wget -O /usr/local/bin/mage https://raw.githubusercontent.com/netz98/n98-magerun/master/n98-magerun.phar
sudo chmod a+x /usr/local/bin/mage
