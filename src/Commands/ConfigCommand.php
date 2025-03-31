<?php

namespace Mccomaschris\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\outro;

#[AsCommand(name: 'config', description: 'Add or update a server or Cloudflare API in ~/.thundr/config.yml')]
class ConfigCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $_SERVER['HOME'] . '/.thundr/config.yml';

        // Ensure directory exists
        if (!file_exists(dirname($configPath))) {
            mkdir(dirname($configPath), 0755, true);
        }

        // Load or create base config
        $config = file_exists($configPath) ? Yaml::parseFile($configPath) : ['servers' => []];

        $choice = select('What would you like to configure?', [
            'Server',
            'Cloudflare'
        ]);

        if ($choice === 'Server') {
            $serverKey = text('Enter a name/key for this server (e.g. prod-1, staging):');

            // If it already exists, confirm overwrite
            if (isset($config['servers'][$serverKey])) {
                $confirmOverwrite = confirm("Server '{$serverKey}' already exists. Overwrite?", default: false);
                if (!$confirmOverwrite) {
                    warning("Aborted.");
                    return Command::SUCCESS;
                }
            }

            $host = text('Host (e.g. 192.168.1.10 or deploy.example.com):');
            $user = text('SSH user:', default: 'thundr');
            $sshKey = text('Path to SSH private key:', default: '~/.ssh/id_rsa');

            $config['servers'][$serverKey] = [
                'host' => $host,
                'user' => $user,
                'ssh_key' => $sshKey,
            ];

            outro("✅ Server '{$serverKey}' saved.");
        }

        if ($choice === 'Cloudflare') {
            $apiToken = text('Cloudflare API token:');
            $accountId = text('Cloudflare Account ID (optional):', default: '');

            $config['cloudflare'] = [
                'api_token' => $apiToken,
                'account_id' => $accountId ?: null,
            ];

            outro("✅ Cloudflare credentials saved.");
        }

        file_put_contents($configPath, Yaml::dump($config, 4, 2));

        return Command::SUCCESS;
    }
}
