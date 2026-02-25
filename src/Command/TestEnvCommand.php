<?php
// src/Command/TestEnvCommand.php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(name: 'app:test-env')]
class TestEnvCommand extends Command
{
    private ParameterBagInterface $params;

    public function __construct(ParameterBagInterface $params)
    {
        parent::__construct();
        $this->params = $params;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Testing Environment Variables:');
        $output->writeln('');
        
        $url = $this->params->get('huggingface_api_url');
        $key = $this->params->get('huggingface_api_key');
        
        $output->writeln('HuggingFace API URL: ' . ($url ?: 'NOT SET'));
        $output->writeln('HuggingFace API Key: ' . ($key ? (substr($key, 0, 10) . '...') : 'NOT SET'));
        
        if (!$url || !$key) {
            $output->writeln('');
            $output->writeln('<error>ERROR: Environment variables not configured!</error>');
            return Command::FAILURE;
        }
        
        $output->writeln('');
        $output->writeln('<info>✓ Environment variables configured correctly</info>');
        return Command::SUCCESS;
    }
}