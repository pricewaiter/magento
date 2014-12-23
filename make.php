<?php

require_once __DIR__ . '/vendor/autoload.php';
use Symfony\Component\Console\Application;
use PriceWaiter\Magento\Console\Command\BuildCommand;
use PriceWaiter\Magento\Console\Command\CheckCommand;
use PriceWaiter\Magento\Console\Command\CleanCommand;

$package = simplexml_load_file('package.xml');
$application = new Application();
$application->setName('PriceWaiter - Magento extension make tool');
$application->setVersion($package->version);
$application->add(new BuildCommand);
$application->add(new CheckCommand);
$application->add(new CleanCommand);
$application->run();
