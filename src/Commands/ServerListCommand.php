<?php

namespace ThundrLabs\ThundrCli\Commands;

use ThundrLabs\ThundrCli\Support\ConfigManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

#[AsCommand(name: 'server:list', description: 'List available servers and project details')]
class ServerListCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $config = ConfigManager::loadGlobalConfig();
        } catch (\RuntimeException $e) {
            $config = ['servers' => []];
        }

        info('Global Servers:');

        $servers = $config['servers'] ?? [];

        if (empty($servers)) {
            warning('No global config found at ~/.thundr/config.yml');
            info('You can create one by running `thundr init`.');

            return Command::SUCCESS;
        }

        table(
            headers: ['Server', 'User', 'Host', 'SSH Key'],
            rows: array_map(function ($key, $server) {
                return [
                    $key,
                    $server['user'],
                    $server['host'],
                    $server['ssh_key'] ?? 'N/A',
                ];
            }, array_keys($config['servers']), $config['servers']),
        );

        return Command::SUCCESS;
    }
}
