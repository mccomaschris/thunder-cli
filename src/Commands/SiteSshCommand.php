<?php

namespace Mccomaschris\ThundrCli\Commands;

use Mccomaschris\ThundrCli\Support\ConfigManager;
use Mccomaschris\ThundrCli\Support\Traits\HandlesEnvironmentSelection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;

#[AsCommand(name: 'site:shell', description: 'SSH into the site server')]
class SiteSshCommand extends Command
{
    use HandlesEnvironmentSelection;

    protected function configure(): void
    {
        $this->configureEnvironmentOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $env = $this->resolveEnvironment($input, $output);
            $project = ConfigManager::loadProjectConfig($env);
            $global = ConfigManager::loadGlobalConfig();
        } catch (\RuntimeException $e) {
            error('❌ '.$e->getMessage());

            return Command::FAILURE;
        }

        $serverKey = $project['server'] ?? null;
        $server = $global['servers'][$serverKey] ?? null;

        if (! $server) {
            error("❌ Server '{$serverKey}' not found in global config.");

            return Command::FAILURE;
        }

        $user = $server['user'] ?? 'thundr';
        $host = $server['host'] ?? null;
        $sshKey = $server['ssh_key'] ?? null;

        if (! $host) {
            error('❌ Host is not defined for this server.');

            return Command::FAILURE;
        }

        $remotePath = "/var/www/html/{$project['root_domain']}/current";
        $sshOptions = $sshKey ? "-i {$sshKey}" : '';

        // Final SSH command with working directory set
        $sshCommand = "ssh {$sshOptions} {$user}@{$host} 'cd {$remotePath} && exec \$SHELL'";

        $status = 0;
        passthru($sshCommand, $status);

        return $status === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
