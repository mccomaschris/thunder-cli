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

#[AsCommand(name: 'site:rollback', description: 'Rollback to the previous release')]
class SiteRollbackCommand extends Command
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

        $releasesDir = "/var/www/html/{$rootDomain}/releases";
        $currentSymlink = "/var/www/html/{$rootDomain}/current";

        // Get the list of releases
        $listCmd = "ls -1t {$releasesDir}";
        $sshList = Process::fromShellCommandline("ssh {$sshOptions} {$user}@{$host} '{$listCmd}'");
        $sshList->run();

        if (!$sshList->isSuccessful()) {
            error("❌ Failed to list releases for rollback.");
            return Command::FAILURE;
        }

        $releases = array_filter(explode(PHP_EOL, trim($sshList->getOutput())));
        if (count($releases) < 2) {
            error("❌ Not enough releases to perform a rollback.");
            return Command::FAILURE;
        }

        $previousRelease = $releases[1];

        if (!confirm("Rollback to previous release: {$previousRelease}?")) {
            info("ℹ️ Rollback cancelled.");
            return Command::SUCCESS;
        }

        $rollbackScript = implode(" && ", [
            "sudo ln -nsf {$releasesDir}/{$previousRelease} {$currentSymlink}",
            "sudo systemctl reload php{$phpVersion}-fpm",
            "sudo systemctl reload nginx"
        ]);

        $sshRollback = Process::fromShellCommandline("ssh {$sshOptions} {$user}@{$host} '{$rollbackScript}'");
        $sshRollback->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (!$sshRollback->isSuccessful()) {
            error("❌ Rollback failed.");
            return Command::FAILURE;
        }

        outro("✅ Rollback complete! Now running: {$previousRelease}");
        return Command::SUCCESS;
    }
}
