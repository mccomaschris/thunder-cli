<?php

namespace Mccomaschris\ThundrCli\Commands;

use Mccomaschris\ThundrCli\Support\ConfigManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand(name: 'server:edit', description: 'Edit a server in your global config')]
class ServerEditCommand extends Command
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
            error('❌ No servers found to edit.');

            return Command::FAILURE;
        }

        $serverKey = select(
            label: 'Which server would you like to edit?',
            options: array_keys($servers)
        );

        $original = $servers[$serverKey];
        $host = text('Host', default: $original['host'] ?? '');
        $user = text('User', default: $original['user'] ?? 'thundr');
        $sshKey = text('Path to SSH Key (optional)', default: $original['ssh_key'] ?? '');

        $servers[$serverKey] = [
            'host' => $host,
            'user' => $user,
            'ssh_key' => $sshKey,
        ];

        $config['servers'] = $servers;

        ConfigManager::saveGlobalConfig($config);

        info("✅ Server '{$serverKey}' updated.");

        return Command::SUCCESS;
    }
}
