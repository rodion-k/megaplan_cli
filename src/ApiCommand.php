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
            ->addOption('method', 'm', InputOption::VALUE_REQUIRED, 'get or post', 'get')
            ->addOption('params', 'p', InputOption::VALUE_REQUIRED)
            ->addOption('jmespath', 'j', InputOption::VALUE_REQUIRED)
            ->addOption('app', 'a', InputOption::VALUE_NONE, 'if username and password are application credentials')
            ->addOption('unquoted', 'u', InputOption::VALUE_NONE, 'specify that any result that is a string will be printed without quotes');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $params = [];
        if ($input->hasOption('params')) {
            parse_str($input->getOption('params'), $params);
        }
        
        $client = new Client($input->getArgument('host'));
        $authMethod = $input->getOption('app') ? 'authApplication' : 'auth';
        $response = $client
            ->{$authMethod}($input->getArgument('username'), $input->getArgument('password'))
            ->{$input->getOption('method')}("/{$input->getArgument('uri')}.api", $params);
        
        if (null !== ($error = $this->getError($client, $response))) {
            /* @var $formatter \Symfony\Component\Console\Helper\FormatterHelper */
            $formatter = $this->getHelper('formatter');
            $output->writeln($formatter->formatBlock($error, 'error'));
            
            return 1;
        }
        
        $output->write($this->prepareResult($input, $response));
    }
    
    protected function getError(Client $client, \stdClass $response)
    {
        return $client->getError()
            ? "{$client->getInfo('http_code')}: {$client->getError()}"
            : ($response->error ?? ($response->status->code === 'error'
                ? $response->status->message
                : null
            ));
    }
    
    protected function prepareResult(InputInterface $input, \stdClass $response): string
    {
        $formatters = [
            'extract_by_jmespath' => function($response) use ($input) {
                return $input->getOption('jmespath')
                    ? \JmesPath\search($input->getOption('jmespath'), $response->data ?? $response)
                    : $response->data ?? $response;
            },
            'encode' => function($result) { return json_encode($result, JSON_PRETTY_PRINT); },
            'unquote' => function($result) use ($input) {
                return $input->getOption('unquoted') ? trim($result, '"'): $result;
            }
        ];
        
        return array_reduce($formatters, function($name, callable $formatter) {
            return $formatter($name);
        }, $response);
    }
}