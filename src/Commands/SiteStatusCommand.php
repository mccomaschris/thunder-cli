<?php

namespace ThundrLabs\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ThundrLabs\ThundrCli\Support\ConfigManager;
use ThundrLabs\ThundrCli\Support\RemoteSshRunner;
use ThundrLabs\ThundrCli\Support\Traits\HandlesEnvironmentSelection;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

#[AsCommand(name: 'site:status', description: 'View the current status of the site (release, SSL, scheduler)')]
class SiteStatusCommand extends Command
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
            error("❌ {$e->getMessage()}");

            return Command::FAILURE;
        }

        $rootDomain = $project['root_domain'];
        $serverKey = $project['server'] ?? null;
        $server = $global['servers'][$serverKey] ?? null;

        if (! $server) {
            error("❌ Server '{$serverKey}' not found in global config.");

            return Command::FAILURE;
        }

        $phpVersion = $project['php_version'] ?? '8.3';
        $deployBase = "/var/www/html/{$rootDomain}";
        $currentReleasePath = "$deployBase/current";
        $sslPem = "/etc/ssl/cloudflare/{$rootDomain}.pem";
        $cronMatch = "{$currentReleasePath}/artisan schedule:run";

        $ssh = RemoteSshRunner::make($server);

        $release = $ssh->run("readlink -f {$currentReleasePath}");
        $ssl = $ssh->run("[ -f {$sslPem} ] && echo 'Cloudflare SSL' || echo 'No Cloudflare SSL found'");
        $scheduler = $ssh->run("crontab -l | grep -q \"{$cronMatch}\" && echo 'Scheduler enabled' || echo 'Scheduler NOT enabled'");

        info("🔧 Current Release:\n{$release}");
        info("ℹ️  PHP Version:\n{$phpVersion}");
        info("🔐 SSL:\n{$ssl}");
        info("⏰ Scheduler:\n{$scheduler}");

        return Command::SUCCESS;
    }
}
