#!/usr/bin/env bash
set -ex

cd "$HOME/build/.modman/pricewaiter"
vendor/bin/magegen clean --ansi
vendor/bin/magegen check --ansi
vendor/bin/magegen build --ansi
vendor/bin/phpunit
