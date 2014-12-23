<?php

namespace PriceWaiter\Magento\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use FileIterator;

class CheckCommand extends Command
{
    private $output;

    protected function configure()
    {
        $this->setName('check')
            ->setDescription('Checks the extension source code for common errors before building')
            ->addOption('--cwd', null, InputOption::VALUE_REQUIRED, 'Set the working directory.', getcwd());
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $dir = $input->getOption('cwd');
        $this->checkXmlFiles($dir);
        $this->checkPhpFiles($dir);
        $this->checkPhtmlFiles($dir);
    }

    public function xmlLint($path)
    {
        $command = new Process('xmllint --noout ' . escapeshellarg($path));
        $command->run();
        if (!$command->isSuccessful()) {
            $this->error($command->getCommandLine());
            $this->output->write($command->getErrorOutput());
            exit($command->getExitCode());
        }
    }

    public function phpLint($path)
    {
        $command = new Process('php -l ' . escapeshellarg($path));
        $command->run();
        if (!$command->isSuccessful()) {
            $this->error($command->getCommandLine());
            $this->output->write($command->getErrorOutput());
            exit($command->getExitCode());
        }
    }

    public function shortTagLint($path)
    {
        $file = new FileIterator($path);
        foreach ($file as $lineno => $line) {
            if (preg_match('/<\?[^p]/', $line)) {
                $this->error("PHP short tag found in {$path} on line {$lineno}");
                exit(-1);
            }
        }
    }

    public function checkPhpFiles($directory)
    {
        $this->output->write('Checking for syntax errors and short tags in PHP files... ');
        $finder = new Finder();
        $iterator = $finder->files()->name('*.php')->in($directory)->notPath('vendor');

        foreach ($iterator as $file) {
            /** @var $file SplFileInfo */
            $realPath = $file->getRealPath();
            $this->phpLint($realPath);
            $this->shortTagLint($realPath);
        }
        $this->success('Done.');
    }

    public function checkPhtmlFiles($directory)
    {
        $this->output->write('Checking for syntax errors and short tags in PHTML files... ');
        $finder = new Finder();
        $iterator = $finder->files()->name('*.phtml')->in($directory)->notPath('vendor');

        foreach ($iterator as $file) {
            /** @var $file SplFileInfo */
            $realPath = $file->getRealPath();
            $this->phpLint($realPath);
            $this->shortTagLint($realPath);
        }
        $this->success('Done.');
    }

    public function checkXmlFiles($directory)
    {
        $this->output->write('Checking for syntax errors in XML files... ');
        $finder = new Finder();
        $iterator = $finder->files()->name('*.xml')->in($directory)->notPath('vendor');

        foreach ($iterator as $file) {
            /** @var $file SplFileInfo */
            $this->xmlLint($file->getRealPath());
        }
        $this->success('Done.');
    }

    public function success($line)
    {
        $this->output->writeln('<info>' . $line . '</info>');
    }

    public function error($line)
    {
        $this->output->writeln('<error>' . $line . '</error>');
    }
}
