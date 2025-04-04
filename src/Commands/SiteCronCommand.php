<?php

namespace ThundrLabs\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ThundrLabs\ThundrCli\Support\ConfigManager;
use ThundrLabs\ThundrCli\Support\RemoteSshRunner;
use ThundrLabs\ThundrCli\Support\Traits\HandlesEnvironmentSelection;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;

#[AsCommand(name: 'site:cron', description: 'Toggle the cron job to run the Laravel scheduler for Artisan commands')]
class SiteCronCommand extends Command
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
        $serverKey = $project['server'] ?? null;
        $server = $global['servers'][$serverKey] ?? null;

        if (! $server) {
            error("❌ Server '{$serverKey}' not found in global config.");

            return Command::FAILURE;
        }

        $ssh = RemoteSshRunner::make($server);

        // Connect to the server to check the cron jobs.
        $phpPath = '/usr/bin/php'; // or detect dynamically
        $cronJobLine = "* * * * * {$phpPath} /var/www/html/{$rootDomain}/current/artisan schedule:run >> /dev/null 2>&1";

        $matchFragment = "/var/www/html/{$rootDomain}/current/artisan schedule:run";

        $checkCronCmd = "crontab -l | grep -q \"{$matchFragment}\""; // 👈 double quotes inside

        $sshCheckCron = $ssh->runWithStatus($checkCronCmd);

        if ($sshCheckCron['success']) {
            if (confirm('The scheduler cron job already exists. Do you want to **remove** it?')) {
                $escapedMatch = escapeshellarg("/var/www/html/{$rootDomain}/current/artisan schedule:run");

                $removeCronCmd = <<<BASH
                ( crontab -l 2>/dev/null | grep -v {$escapedMatch} ) | crontab -
                BASH;

                // Escape for remote execution
                info('🚀 Removing scheduler cron job...');
                $removeCron = $ssh->runWithStatus($removeCronCmd);

                if ($removeCron['success']) {
                    outro('✅ Cron job removed.');
                } else {
                    error('❌ Failed to remove cron job.');
                    info($removeCron['output']); // optional: show output for debug
                }
            } else {
                outro('❌ Cron job not removed.');
            }

            return Command::SUCCESS;
        } else {
            if (confirm('The scheduler cron job is not set. Do you want to **add** it?')) {
                info('🚀 Adding scheduler cron job...');
                $addCron = $ssh->runWithStatus('(crontab -l 2>/dev/null; echo "'.$cronJobLine.'") | crontab -');

                if ($addCron['success']) {
                    outro('✅ Cron job added.');
                } else {
                    error('❌ Failed to add cron job.');
                }
            } else {
                outro('❌ Cron job not added.');
            }
        }

        return Command::SUCCESS;
    }
}
