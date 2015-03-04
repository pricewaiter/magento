<?php

namespace PriceWaiter\Magento\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BuildCommand extends Command
{
    protected function configure()
    {
        $this->setName('build')
            ->setDescription('Builds a standalone package for Magento Connect')
            ->addOption('--package-xml-file', null, InputOption::VALUE_REQUIRED, 'Specifies the path to the extension package.xml file.', 'package.xml')
            ->addOption('--output-directory', null, InputOption::VALUE_REQUIRED, 'Specifies the directory where the built extension file should be placed.', getcwd());
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->write('Building assets... ');
        `cake build`;
        $output->writeln('<info>Done.</info>');

        $output->write('Building package... ');

        //Load Magento core
        $mageFile = realpath(getcwd() . '/../../app/Mage.php');
        require_once $mageFile;

        //Boilerplate
        umask(0);
        \Mage::app();

        //Build package
        $package = new \Mage_Connect_Package($input->getOption('package-xml-file'));
        $package->save($input->getOption('output-directory'));

        $output->writeln('<info>Done.</info>');
    }
}
