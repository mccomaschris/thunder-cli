<?php

namespace ThundrLabs\ThundrCli\Commands;

use ThundrLabs\ThundrCli\Support\ConfigManager;
use ThundrLabs\ThundrCli\Support\RemoteSshRunner;
use ThundrLabs\ThundrCli\Support\Traits\HandlesEnvironmentSelection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

#[AsCommand(name: 'site:sqlite', description: 'Add SSL (Cloudflare or Let\'s Encrypt) to site and configure nginx')]
class SiteSqliteCommand extends Command
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
            error('❌ Server not found in global config.');

            return Command::FAILURE;
        }

        $basePath = "/var/www/html/{$rootDomain}";
        $sharedDbPath = "{$basePath}/shared/database";
        $sharedDbFile = "{$sharedDbPath}/database.sqlite";
        $currentDbPath = "{$basePath}/current/database";

        $ssh = RemoteSshRunner::make($server);

        // Check if the shared database directory already exists
        $checkDbFileCmd = "if [ ! -d {$sharedDbPath} ]; then echo 'not_exists'; else echo 'exists'; fi";
        $checkDbResult = trim($ssh->run($checkDbFileCmd));

        if ($checkDbResult === 'exists') {
            error('❌ Shared database already exists.');

            return Command::FAILURE;
        }

        info('Creating shared database directory and SQLite file...');

        $commands = [
            "sudo mkdir -p {$sharedDbPath}",
            "sudo touch {$sharedDbFile}",
            "sudo chmod 664 {$sharedDbFile}",
            "sudo chown thundr:www-data {$sharedDbFile} || true",
        ];

        $ssh->run(implode(' && ', $commands));

        info('Linking shared database to current release...');

        $ssh->run("sudo rm -rf {$currentDbPath}");
        $ssh->run("sudo ln -s {$sharedDbFile} {$currentDbPath}");

        return Command::SUCCESS;
    }
}
