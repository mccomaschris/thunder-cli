<?php

namespace Mccomaschris\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;

#[AsCommand(name: 'site:status', description: 'View the current status of the site (release, SSL, scheduler)')]
class SiteStatusCommand extends Command
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

        $phpVersion = $project['php_version'] ?? '8.3';
        $deployBase = "/var/www/html/{$rootDomain}";
        $currentReleasePath = "$deployBase/current";
        $sslPem = "/etc/ssl/cloudflare/{$rootDomain}.pem";

        // Get current release symlink target
        $releaseCmd = "readlink -f {$currentReleasePath}";
        $sslCmd = "[ -f {$sslPem} ] && echo 'Cloudflare SSL' || echo 'No Cloudflare SSL found'";
        $match = "/var/www/html/{$rootDomain}/current/artisan schedule:run";
        $cronCheck = "crontab -l | grep -q \"{$match}\" && echo 'Scheduler enabled' || echo 'Scheduler NOT enabled'";

        $combined = "echo '🔧 Current Release:' && {$releaseCmd} && echo '' && echo '🔐 SSL:' && {$sslCmd} && echo '' && echo '⏰ Scheduler:' && {$cronCheck}";

        $sshCmd = "ssh {$sshOptions} {$user}@{$host} '{$combined}'";
        $process = Process::fromShellCommandline($sshCmd);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}
