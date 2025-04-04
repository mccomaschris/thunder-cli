<?php

namespace ThundrLabs\ThundrCli\Commands;

use ThundrLabs\ThundrCli\Support\ConfigManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

#[AsCommand(name: 'server:delete', description: 'Delete a server from your global config')]
class ServerDeleteCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $config = ConfigManager::loadGlobalConfig();
        } catch (\RuntimeException $e) {
            error('❌ '.$e->getMessage());

            return Command::FAILURE;
        }

        $configPath = ConfigManager::globalConfigPath();

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

        ConfigManager::saveGlobalConfig($config);

        info("✅ Server '{$serverKey}' deleted from config.");

        return Command::SUCCESS;
    }
}
