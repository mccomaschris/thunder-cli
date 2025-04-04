<?php

namespace Mccomaschris\ThundrCli\Commands;

use Mccomaschris\ThundrCli\Support\ConfigManager;
use Mccomaschris\ThundrCli\Support\RemoteSshRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

#[AsCommand(name: 'server:ssh', description: 'SSH into a server defined in your global Thundr config')]
class ServerSshConnectCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $globalConfig = ConfigManager::loadGlobalConfig();
        $servers = $globalConfig['servers'] ?? [];

        if (empty($servers)) {
            error('❌ No servers defined in global config.');

            return Command::FAILURE;
        }

        $selectedServer = select(
            label: 'Which server would you like to SSH into?',
            options: array_keys($servers)
        );

        $server = $servers[$selectedServer] ?? null;

        if (! $server) {
            error("❌ Server config for '{$selectedServer}' not found.");

            return Command::FAILURE;
        }

        $ssh = RemoteSshRunner::make($server);
        $sshCommand = $ssh->buildRawLoginCommand();

        info("🔗 Connecting to {$server['user']}@{$server['host']}...\n");

        passthru($sshCommand);

        return Command::SUCCESS;
    }
}
