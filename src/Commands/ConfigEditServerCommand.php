<?php

namespace Mccomaschris\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand(name: 'config:edit', description: 'Edit a server in your global config')]
class ConfigEditServerCommand extends Command
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
            error('❌ No servers defined in global config.');

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
        file_put_contents($configPath, Yaml::dump($config, 4));
        info("✅ Server '{$serverKey}' updated.");

        return Command::SUCCESS;
    }
}
