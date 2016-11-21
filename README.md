# PriceWaiter Magento Extension

## Development Flow

0. Ensure your Docker machine is accessible as **magento**: `echo "$(docker-machine ip dev) magento" >> /etc/hosts`
1. Start the docker environment up: `docker-compose up`
    1. Magento 1.9 + PHP 5.5 available on [http://magento:1955](http://magento:1955) (Username: `admin`, password: `password123`)
2. Write code :saxophone:
3. Run PHP Code Sniffer :nose:
4. Run tests :tada:

## PHP Code Sniffer

`bin/phpcs` will run PHP Code Sniffer against the extension source. `bin/phpcs --help` to see the available options, but the most useful variation is probably **`bin/phpcs --severity=10`**, which is the level we need to pass for Magento Technical Review.

Also, `bin/phpcbf` will run the Code Beautifier against the source and fix any warnings / errors it can :sparkles: automatically :sparkles:

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

## Sending Mail

By default, the dev container **does not** send email. Getting it to do so involves some minor hacking of the Magento source.

First, modify the `getMail()` method in `app/code/core/Mage/Core/Model/Email/Template.php` to look like this:

```php
    /**
     * Retrieve mail object instance
     *
     * @return Zend_Mail
     */
    public function getMail()
    {
        if (is_null($this->_mail)) {
            // BEGIN HACK: Send to external SMTP server
            $host = 'mailcatcher';
            $port = 1025;
            $transport = new Zend_Mail_Transport_Smtp($host, compact('port'));
            Zend_Mail::setDefaultTransport($transport);
            // END HACK
            $this->_mail = new Zend_Mail('utf-8');
        }
        return $this->_mail;
    }
```

Then, ensure the `v19php55` container can talk to a `mailcatcher` instance via the hostname `mailcatcher`
(you can use `extra_hosts` or `external_links` in `docker-compose.yml` for this).

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
