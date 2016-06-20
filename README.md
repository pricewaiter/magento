# PriceWaiter Magento Extension

## Development Flow

0. Ensure your Docker machine is accessible as **magento**: `echo "$(docker-machine ip dev) magento" >> /etc/hosts`
1. Start the docker environment up: `docker-compose up`
    1. Magento 1.9 + PHP 5.3 available on [http://magento:1955](http://magento:1955) (Username: `admin`, password: `password123`)
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

## Connecting to PriceWaiter

To get data flowing between Magento and PriceWaiter running locally, you need to tell them about each other. This is best accomplished using [`extra_hosts` in the `docker-compose.yml` file](https://docs.docker.com/compose/compose-file/#extra-hosts).

## Releasing a New Version

### 1. Update the Version Number

`bin/bump-version` will set a new version number in all the places one needs to be set, then commit + tag the changes. `git push --tags` afterward and you'll be set.

### 2. Build a Tarball and Upload to Magento Connect

`bin/build-tarball` will put a `.tgz` file in the `build/` directory. Note that the build script runs *inside* the Docker environment, so it will need to be running.

### 3. Push Changes to the Public Repo

First, add the `public-repo` git remote:

`git remote add git@github.com:pricewaiter/magento.git`

`bin/publish` will push a squashed commit with all changes for the version to the public Magento repo.
This `README.md` file will be replaced with the `README-public.md` file.
