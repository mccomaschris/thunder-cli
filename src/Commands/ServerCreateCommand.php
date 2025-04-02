<?php

namespace Mccomaschris\ThundrCli\Commands;

use Mccomaschris\ThundrCli\Support\ConfigManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[AsCommand(name: 'server:create', description: 'Add or update a server or Cloudflare API in ~/.thundr/config.yml')]
class ServerCreateCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $config = ConfigManager::loadGlobalConfig();
        } catch (\RuntimeException $e) {
            $config = ['servers' => []];
        }

        $serverKey = text('Enter a name/key for this server (e.g. prod-1, staging):');

        if (isset($config['servers'][$serverKey])) {
            $confirmOverwrite = confirm("Server '{$serverKey}' already exists. Overwrite?", default: false);
            if (! $confirmOverwrite) {
                warning('Aborted.');

                return Command::SUCCESS;
            }
        }

        $host = text('Host (e.g. 192.168.1.10 or deploy.example.com):');
        $user = text('SSH user:', default: 'thundr');
        $sshKey = text('Path to SSH private key:', default: '~/.ssh/id_rsa');
        $operatingSystem = strtolower(select('Operating System:', ['Ubuntu', 'Oracle']));

        $config['servers'][$serverKey] = [
            'host' => $host,
            'user' => $user,
            'ssh_key' => $sshKey,
            'os' => $operatingSystem,
        ];

        ConfigManager::saveGlobalConfig($config);

        outro("✅ Server '{$serverKey}' saved.");

        return Command::SUCCESS;
    }
}
