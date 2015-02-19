#!/usr/bin/env bash
set -ex

cd "$HOME/build/.modman/pricewaiter"
php make.php clean --ansi
php make.php check --ansi
php make.php build --ansi
vendor/bin/phpunit
