<?php

namespace Mccomaschris\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

use function Laravel\Prompts\error;

#[AsCommand(name: 'site:ssh', description: 'SSH into the site server')]
class SiteSshCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cwd = getcwd();
        $projectYaml = $cwd.'/thundr.yml';
        $globalYaml = ($_SERVER['HOME'] ?? getenv('HOME') ?: getenv('USERPROFILE')).'/.thundr/config.yml';

        if (! file_exists($projectYaml) || ! file_exists($globalYaml)) {
            error('❌ Missing thundr.yml or ~/.thundr/config.yml');

            return Command::FAILURE;
        }

        $project = Yaml::parseFile($projectYaml);
        $global = Yaml::parseFile($globalYaml);

        $serverKey = $project['server'] ?? null;
        $server = $global['servers'][$serverKey] ?? null;

        if (! $server) {
            error("❌ Server '{$serverKey}' not found in global config.");

            return Command::FAILURE;
        }

        $user = $server['user'] ?? 'thundr';
        $host = $server['host'];
        $sshKey = $server['ssh_key'] ?? null;

        $remotePath = "/var/www/html/{$project['root_domain']}/current";

        $sshOptions = $sshKey ? "-i {$sshKey}" : '';
        $sshCommand = "ssh {$sshOptions} {$user}@{$host}";

        $sshCommand = "ssh {$sshOptions} {$user}@{$host} 'cd {$remotePath} && exec \$SHELL'";

        $status = 0;

        passthru($sshCommand, $status);

        return $status === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
