<?php

namespace ThundrLabs\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ThundrLabs\ThundrCli\Support\ConfigManager;
use ThundrLabs\ThundrCli\Support\Traits\HandlesEnvironmentSelection;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

#[AsCommand(name: 'site:logs', description: 'Tail the Laravel log file on the site')]
class SiteLogsCommand extends Command
{
    use HandlesEnvironmentSelection;

    protected function configure(): void
    {
        $this->configureEnvironmentOption();
        $this->addOption('lines', 'l', InputOption::VALUE_OPTIONAL, 'Number of lines to show initially', 50);
        $this->addOption('file', 'f', InputOption::VALUE_OPTIONAL, 'Log file name (defaults to laravel.log)', 'laravel.log');
        $this->addOption('grep', 'g', InputOption::VALUE_OPTIONAL, 'Grep filter to apply to the log output');
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
        $host = $server['host'];
        $sshKey = $server['ssh_key'] ?? null;
        $sshOptions = $sshKey ? "-i {$sshKey}" : '';

        $rootDomain = $project['root_domain'];
        $lines = (int) $input->getOption('lines');
        $file = $input->getOption('file');
        $grep = $input->getOption('grep');

        $logPath = escapeshellarg("/var/www/html/{$rootDomain}/current/storage/logs/{$file}");
        $cmd = "tail -n {$lines} -f {$logPath}";

        if ($grep) {
            $cmd .= ' | grep --line-buffered '.escapeshellarg($grep);
        }

        $sshCommand = "ssh {$sshOptions} {$user}@{$host} {$cmd}";

        info("📡 Tailing logs for {$rootDomain} ({$file})... Press Ctrl+C to stop.\n");

        passthru($sshCommand, $exitCode);

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
