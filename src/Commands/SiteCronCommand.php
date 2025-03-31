<?php

namespace Mccomaschris\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\warning;

#[AsCommand(name: 'site:cron', description: 'Toggle the cron job to run the Laravel scheduler for Artisan commands')]
class SiteCronCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cwd = getcwd();
        $projectYaml = $cwd . '/thundr.yml';
        $globalYaml = ($_SERVER['HOME'] ?? getenv('HOME') ?: getenv('USERPROFILE')) . '/.thundr/config.yml';

        if (!file_exists($projectYaml) || !file_exists($globalYaml)) {
            error("❌ Missing thundr.yml or ~/.thundr/config.yml");
            return Command::FAILURE;
        }

        $project = Yaml::parseFile($projectYaml);
        $global = Yaml::parseFile($globalYaml);

        $rootDomain = $project['root_domain'];
        $phpVersion = $project['php_version'] ?? '8.3';
        $serverKey = $project['server'] ?? null;
        $server = $global['servers'][$serverKey] ?? null;

        if (!$server) {
            error("❌ Server '{$serverKey}' not found in global config.");
            return Command::FAILURE;
        }

        $user = $server['user'] ?? 'thundr';
        $host = $server['host'];
        $sshKey = $server['ssh_key'] ?? null;
        $sshOptions = $sshKey ? "-i {$sshKey}" : '';

        // Connect to the server to check the cron jobs.
        $phpPath = "/usr/bin/php"; // or detect dynamically
        $cronJobLine = "* * * * * {$phpPath} /var/www/html/{$rootDomain}/current/artisan schedule:run >> /dev/null 2>&1";

        $matchFragment = "/var/www/html/{$rootDomain}/current/artisan schedule:run";
        $checkCronCmd = "crontab -l | grep -q \"{$matchFragment}\""; // 👈 double quotes inside

        $sshCheckCron = Process::fromShellCommandline("ssh {$sshOptions} {$user}@{$host} '{$checkCronCmd}'");
        $sshCheckCron->run();

        if ($sshCheckCron->isSuccessful()) {
            if (confirm("The scheduler cron job already exists. Do you want to **remove** it?")) {
                $matchFragment = "/var/www/html/{$rootDomain}/current/artisan schedule:run";
                $escapedMatch = escapeshellarg($matchFragment); // single-quoted string

                $removeCronCmd = <<<CMD
                TMP_CRON=\$(mktemp) && \
                crontab -l | grep -v {$escapedMatch} > \$TMP_CRON && \
                LINES=\$(grep -c '^[^#]' \$TMP_CRON) && \
                if [ \$LINES -gt 0 ]; then crontab \$TMP_CRON; else echo '🛑 Aborted: this would remove all crons'; fi
                CMD;

                // Escape for remote execution
                $sshCmd = "ssh {$sshOptions} {$user}@{$host} " . escapeshellarg($removeCronCmd);

                info("🚀 Removing scheduler cron job...");
                $removeCron = Process::fromShellCommandline($sshCmd);
                $removeCron->run();

                if ($removeCron->isSuccessful()) {
                    outro("✅ Cron job removed.");
                } else {
                    error("❌ Failed to remove cron job.");
                }
            } else {
                outro("❌ Cron job not removed.");
            }
            return Command::SUCCESS;
        } else {
            if (confirm("The scheduler cron job is not set. Do you want to **add** it?")) {
                $addCronCmd = "(crontab -l 2>/dev/null; echo \"" . $cronJobLine . "\") | crontab -";
                $sshCmd = "ssh {$sshOptions} {$user}@{$host} '" . $addCronCmd . "'";

                info("🚀 Adding scheduler cron job...");
                $addCron = Process::fromShellCommandline($sshCmd);
                $addCron->run();

                if ($addCron->isSuccessful()) {
                    outro("✅ Cron job added.");
                } else {
                    error("❌ Failed to add cron job.");
                }
            } else {
                outro("❌ Cron job not added.");
            }
        }

        return Command::SUCCESS;
    }
}
