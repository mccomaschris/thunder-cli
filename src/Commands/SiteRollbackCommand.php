<?php

namespace ThundrLabs\ThundrCli\Commands;

use ThundrLabs\ThundrCli\Support\ConfigManager;
use ThundrLabs\ThundrCli\Support\RemoteSshRunner;
use ThundrLabs\ThundrCli\Support\Traits\HandlesEnvironmentSelection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;

#[AsCommand(name: 'site:rollback', description: 'Rollback to the previous release')]
class SiteRollbackCommand extends Command
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

        $rootDomain = $project['root_domain'];
        $phpVersion = $project['php_version'] ?? '8.3';
        $serverKey = $project['server'] ?? null;
        $server = $global['servers'][$serverKey] ?? null;

        if (! $server) {
            error("❌ Server '{$serverKey}' not found in global config.");

            return Command::FAILURE;
        }

        $ssh = RemoteSshRunner::make($server);
        $releasesDir = "/var/www/html/{$rootDomain}/releases";
        $currentSymlink = "/var/www/html/{$rootDomain}/current";

        // Get the list of releases
        $listCmd = "ls -1t {$releasesDir}";
        $sshList = $ssh->runWithStatus($listCmd);

        if (! $sshList['success']) {
            error('❌ Failed to list releases for rollback.');

            return Command::FAILURE;
        }

        $releases = array_filter(preg_split('/\r\n|\r|\n/', trim($sshList['output'])));

        if (count($releases) < 2) {
            error('❌ Not enough releases to perform a rollback.');

            return Command::FAILURE;
        }

        $previousRelease = $releases[1];

        if (! confirm("Rollback to previous release: {$previousRelease}?")) {
            info('ℹ️ Rollback cancelled.');

            return Command::SUCCESS;
        }

        $rollbackScript = implode(' && ', [
            "sudo ln -nsf {$releasesDir}/{$previousRelease} {$currentSymlink}",
            "sudo systemctl reload php{$phpVersion}-fpm",
            'sudo systemctl reload nginx',
        ]);

        $sshRollback = $ssh->runWithStatus($rollbackScript);

        if (! $sshRollback['success']) {
            error('❌ Rollback failed.');

            return Command::FAILURE;
        }

        outro("✅ Rollback complete! Now running: {$previousRelease}");

        return Command::SUCCESS;
    }
}
