<?php

namespace Mccomaschris\ThundrCli\Commands;

use Mccomaschris\ThundrCli\Support\ConfigLoader;
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
        $configPath = $_SERVER['HOME'].'/.thundr/config.yml';

        try {
            $config = ConfigLoader::loadGlobalConfig();
        } catch (\RuntimeException $e) {
            error('❌ '.$e->getMessage());

            return Command::FAILURE;
        }

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

        file_put_contents($configPath, Yaml::dump($config, 4, 2));
        info("✅ Server '{$serverKey}' deleted from config.");

        return Command::SUCCESS;
    }
}
