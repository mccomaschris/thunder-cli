<?php

namespace ThundrLabs\ThundrCli\Commands;

use ThundrLabs\ThundrCli\Support\ConfigManager;
use ThundrLabs\ThundrCli\Support\RemoteSshRunner;
use ThundrLabs\ThundrCli\Support\Traits\HandlesEnvironmentSelection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

#[AsCommand(name: 'site:artisan', description: 'Run a remote artisan command on the server')]
class SiteArtisanCommand extends Command
{
    use HandlesEnvironmentSelection;

    protected function configure(): void
    {
        $this->configureEnvironmentOption();
        $this->addArgument('artisan_command', InputArgument::REQUIRED, 'The artisan command to run (e.g. migrate, config:cache)');
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

        $command = $input->getArgument('artisan_command');
        $remotePath = "/var/www/html/{$rootDomain}/current";

        info("🎯 Running: php artisan {$command} on {$rootDomain}...");

        $outputText = $ssh->run("cd {$remotePath} && php artisan {$command}");

        if ($outputText === false) {
            error('❌ Command failed.');

            return Command::FAILURE;
        }

        $output->writeln($outputText);

        return Command::SUCCESS;
    }
}
