<?php

namespace Mccomaschris\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

#[AsCommand(name: 'config:delete', description: 'Delete a server from your global config')]
class ConfigDeleteServerCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = ($_SERVER['HOME'] ?? getenv('HOME') ?: getenv('USERPROFILE')).'/.thundr/config.yml';

        if (! file_exists($configPath)) {
            error('❌ Global config file not found at ~/.thundr/config.yml');

            return Command::FAILURE;
        }

        $config = Yaml::parseFile($configPath);
        $servers = $config['servers'] ?? [];

        if (empty($servers)) {
            error('❌ No servers found to delete.');

            return Command::FAILURE;
        }

        $serverKey = select(
            label: 'Which server would you like to delete?',
            options: array_keys($servers)
        );

        if (! confirm("Are you sure you want to delete '{$serverKey}'? This action cannot be undone.")) {
            info('❌ Deletion cancelled.');

            return Command::SUCCESS;
        }

        unset($config['servers'][$serverKey]);
        file_put_contents($configPath, Yaml::dump($config, 4));
        info("✅ Server '{$serverKey}' deleted from config.");

        return Command::SUCCESS;
    }
}
