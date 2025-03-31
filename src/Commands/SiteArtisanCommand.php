<?php

namespace Mccomaschris\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

#[AsCommand(name: 'site:artisan', description: 'Run a remote artisan command on the server')]
class SiteArtisanCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('artisan_command', InputArgument::REQUIRED, 'The artisan command to run (e.g. migrate, config:cache)');
    }

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

        $rootDomain = $project['root_domain'];
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
        $command = $input->getArgument('artisan_command');

        $remotePath = "/var/www/html/{$rootDomain}/current";
        $sshCommand = "cd {$remotePath} && php artisan {$command}";

        info("🎯 Running: artisan {$command} on {$host}...");

        $process = Process::fromShellCommandline("ssh {$sshOptions} {$user}@{$host} '{$sshCommand}'");
        $process->setTimeout(300);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}
