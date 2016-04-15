# PriceWaiter Magento Extension

## Development Flow

0. Ensure your Docker machine is accessible as **magento.local**: `echo "$(docker-machine ip dev) magento.local" >> /etc/hosts`
1. Start the docker environment up: `docker-compose up`
    1. Magento 1.9 + PHP 5.5 available on [http://magento.local:1955](http://magento.local:1955) (Username: `admin`, password: `password123`)
2. Write code :saxophone:
3. Run tests :tada:

## Tests

Tests run inside Docker containers. There are 2 sets of tests:

#### Unit Tests (`bin/run-unit-tests`)

Runs `phpunit --testsuite=unit` (see `tests/unit`). Unit tests should not touch the database. *Ideally* they don't touch any Magento code at all.

#### Integration Tests (`bin/run-integration-tests`)

Runs `phpunit --testsuite=integration` (see `tests/integration`). Integration tests can interact with an installed Magento system.

**HINT:** Any arguments you pass to these scripts will be forwarded to `phpunit`.

## phpMyAdmin

phpMyAdmin runs on [http://magento.local:7777](http://magento.local:7777). There is a separate database for each Magento installation.

## Releasing a New Version

_TODO: Write this part._
