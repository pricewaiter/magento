# PriceWaiter Magento Extension

## Development Flow

0. Ensure your Docker machine is accessible as **magento**: `echo "$(docker-machine ip dev) magento" >> /etc/hosts`
1. Start the docker environment up: `docker-compose up`
    1. Magento 1.9 + PHP 5.5 available on [http://magento:1955](http://magento:1955) (Username: `admin`, password: `password123`)
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

phpMyAdmin runs on [http://magento:7777](http://magento:7777). There is a separate database for each Magento installation.

## Using Magento Connect

To set filesystem permissions to enable using Magento Connect, run the `enable-magento-connect` script inside the container:

```
docker exec -it magento_v19php55 enable-magento-connect
```

To reset filesystem permissions afterward, use `reset-magento-permissions`:

```
docker exec -it magento_v19php55 reset-magento-permissions
```

## Releasing a New Version

_TODO: Write this part._
