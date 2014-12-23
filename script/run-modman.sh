#!/usr/bin/env bash
set -ex

cd $HOME/build
modman init
modman link $TRAVIS_BUILD_DIR
