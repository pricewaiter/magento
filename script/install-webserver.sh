#!/usr/bin/env bash
set -ex

# install packages
sudo apt-get install apache2 libapache2-mod-fastcgi

# enable php-fpm
sudo cp ~/.phpenv/versions/$(phpenv version-name)/etc/php-fpm.conf.default ~/.phpenv/versions/$(phpenv version-name)/etc/php-fpm.conf
sudo a2enmod rewrite actions fastcgi alias
phpenv config-add "$TRAVIS_BUILD_DIR/travis/travis.php.ini"
~/.phpenv/versions/$(phpenv version-name)/sbin/php-fpm

# configure apache virtual hosts
sudo cp -f travis/virtualhost.conf /etc/apache2/sites-available/default
sudo sed -e "s?%TRAVIS_BUILD_DIR%?$(pwd)/build?g" --in-place /etc/apache2/sites-available/default
sudo sed -e "s?%VIRTUALHOST_NAME%?${VIRTUALHOST_NAME}?g" --in-place /etc/apache2/sites-available/default
sudo service apache2 restart
