<?php

namespace PriceWaiter\Magento\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanCommand extends Command
{
    protected function configure()
    {
        $this->setName('clean')
            ->setDescription('Deletes the compiled package .tgz and other temporary files');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->write('Clearing var/ directory, deleting existing package file, .DS_Store files, and .un~ files... ');
        `rm -rf var/`;
        `find . -name "*.un~" -delete`;
        `find . -name ".DS_Store" -delete`;
        `rm -f nypwidget-*tgz`;
        $output->writeln('<info>Done</info>');
    }
}
