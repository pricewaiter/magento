#!/usr/bin/env bash
set -ex

# enable php-fpm
sudo cp ~/.phpenv/versions/$(phpenv version-name)/etc/php-fpm.conf.default ~/.phpenv/versions/$(phpenv version-name)/etc/php-fpm.conf
phpenv config-add "$TRAVIS_BUILD_DIR/travis/travis.php.ini"
~/.phpenv/versions/$(phpenv version-name)/sbin/php-fpm

# configure nginx server
sudo cp -f travis/nginx-server.conf /etc/nginx/sites-available/default
sudo sed -e "s?%TRAVIS_BUILD_DIR%?$HOME/build?g" --in-place /etc/nginx/sites-available/default
sudo sed -e "s?%VIRTUALHOST_NAME%?${VIRTUALHOST_NAME}?g" --in-place /etc/nginx/sites-available/default
sudo service nginx restart
