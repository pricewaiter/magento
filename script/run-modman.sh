#!/usr/bin/env bash
set -ex

cd $HOME/build
modman init
cp -R $TRAVIS_BUILD_DIR .modman/pricewaiter
cd .modman/pricewaiter
modman deploy

mage cache:clean
mage cache:flush
mage index:reindex:all
mage sys:info
mage sys:modules:list
