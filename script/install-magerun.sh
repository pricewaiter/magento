#!/usr/bin/env bash
set -ex

# Download and install n98-magerun
sudo wget -O /usr/local/bin/mage http://files.magerun.net/n98-magerun-latest.phar
sudo chmod a+x /usr/local/bin/mage
