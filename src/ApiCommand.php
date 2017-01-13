<?php

namespace MegaplanCLI;

use Megaplan\SimpleClient\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ApiCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('api')
            ->addArgument('host', InputArgument::REQUIRED)
            ->addArgument('username', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::REQUIRED)
            ->addArgument('uri', InputArgument::REQUIRED)
            ->addOption('method', 'm', InputOption::VALUE_OPTIONAL, 'get or post', 'get')
            ->addOption('params', 'p', InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $params = [];
        if ($input->hasOption('params')) {
            parse_str($input->getOption('params'), $params);
        }
        
        $client = new Client($input->getArgument('host'));
        $response = $client
            ->auth($input->getArgument('username'), $input->getArgument('password'))
            ->{$input->getOption('method')}("/{$input->getArgument('uri')}.api", $params);
        
        $output->write(print_r($response, 1));
        
        $error = $client->getError()
            ? "{$client->getInfo('http_code')}: {$client->getError()}"
            : ($response->error ?? ($response->status->code === 'error'
                ? $response->status->message
                : null
            ));
        
        if (null !== $error) {
            /* @var $formatter \Symfony\Component\Console\Helper\FormatterHelper */
            $formatter = $this->getHelper('formatter');
            $output->writeln($formatter->formatBlock($error, 'error'));
            
            return 1;
        }
    }
}