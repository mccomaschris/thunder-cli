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
use function Laravel\Prompts\outro;

#[AsCommand(name: 'site:create', description: 'Provision a new site on the remote server')]
class SiteCreateCommand extends Command
{
    use HandlesEnvironmentSelection;

    protected function configure(): void
    {
        $this->configureEnvironmentOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectPath = ConfigManager::projectConfigPath();

        if (! file_exists($projectPath)) {
            if (confirm('❓ No thundr.yml found. Would you like to generate one now?', default: true)) {
                $initCommand = $this->getApplication()?->find('site:init');

                if (! $initCommand) {
                    error('❌ Could not find the site:init command.');

                    return Command::FAILURE;
                }

                $exitCode = $initCommand->run($input, $output);

                if ($exitCode !== Command::SUCCESS || ! file_exists($projectPath)) {
                    error('❌ Failed to create thundr.yml. Aborting.');

                    return Command::FAILURE;
                }
            } else {
                error('❌ Cannot continue without thundr.yml.');

                return Command::FAILURE;
            }
        }

        try {
            $env = $this->resolveEnvironment($input, $output);
            $project = ConfigManager::loadProjectConfig($env);
            $global = ConfigManager::loadGlobalConfig();
        } catch (\RuntimeException $e) {
            error('❌ '.$e->getMessage());

            return Command::FAILURE;
        }

        $serverKey = $project['server'];
        $server = $global['servers'][$serverKey] ?? null;

        if (! $server) {
            error("❌ Server '{$serverKey}' not found.");

            return Command::FAILURE;
        }

        $ssh = RemoteSshRunner::make($server);
        $rootDomain = $project['root_domain'];
        $phpVersion = $project['php_version'] ?? '8.3';
        $nakedRedirect = $project['naked_redirect'] ?? false;
        $deployBase = "/var/www/html/{$rootDomain}";
        $os = $global['servers'][$project['server']]['os'] ?? 'ubuntu';
        $webGroup = $os === 'oracle' ? 'nginx' : 'www-data';
        $user = $server['user'] ?? 'thundr';

        $commands = [
            "sudo mkdir -p {$deployBase}/releases",
            "sudo mkdir -p {$deployBase}/shared/storage",
            "sudo mkdir -p {$deployBase}/shared/public/uploads",
        ];

        if (($project['database'] ?? null) === 'sqlite') {
            $commands[] = "sudo mkdir -p {$deployBase}/shared/database";
            $commands[] = "sudo touch {$deployBase}/shared/database/database.sqlite";
            $commands[] = "sudo chown -R {$user}:{$webGroup} {$deployBase}/shared/database";
        }

        // Load main nginx config stub
        $mainStubPaths = [
            __DIR__.'/../../../resources/stubs/nginx.stub',
            __DIR__.'/../../resources/stubs/nginx.stub',
        ];

        $stubPath = null;
        foreach ($mainStubPaths as $path) {
            if (file_exists($path)) {
                $stubPath = realpath($path);
                break;
            }
        }

        if (! $stubPath) {
            error("❌ Nginx stub not found. Looked in:\n".implode("\n", $mainStubPaths));

            return Command::FAILURE;
        }

        $stub = file_get_contents($stubPath);
        $serverType = strtolower($project['server_type'] ?? 'ubuntu');
        $phpSocket = $serverType === 'oracle' ? 'php-fpm.sock' : "php{$phpVersion}-fpm.sock";

        $nginxConfig = str_replace(
            ['{{ root_domain }}', '{{ php_version }}', '{{ php_socket }}'],
            [$rootDomain, $phpVersion, $phpSocket],
            $stub
        );

        $localTmp = "/tmp/nginx_{$rootDomain}.conf";
        file_put_contents($localTmp, $nginxConfig);
        $remoteTmp = "/tmp/{$rootDomain}.conf";
        $ssh->upload($localTmp, $remoteTmp);

        $commands[] = "sudo mv {$remoteTmp} /etc/nginx/sites-available/{$rootDomain}";
        $commands[] = "sudo ln -sf /etc/nginx/sites-available/{$rootDomain} /etc/nginx/sites-enabled/{$rootDomain}";

        // Handle naked domain redirect config
        if (str_starts_with($rootDomain, 'www.')) {
            $nakedDomain = str_replace('www.', '', $rootDomain);
        } else {
            $nakedDomain = null;
        }

        if ($nakedRedirect && isset($nakedDomain)) {
            $nakedStubPaths = [
                __DIR__.'/../../../resources/stubs/nginx-naked.stub',
                __DIR__.'/../../resources/stubs/nginx-naked.stub',
            ];

            $nakedStubPath = null;
            foreach ($nakedStubPaths as $path) {
                if (file_exists($path)) {
                    $nakedStubPath = realpath($path);
                    break;
                }
            }

            if (! $nakedStubPath) {
                error("❌ Nginx naked domain redirect stub not found. Looked in:\n".implode("\n", $nakedStubPaths));

                return Command::FAILURE;
            }

            $nakedStub = file_get_contents($nakedStubPath);
            $nakedConfig = str_replace(
                ['{{ naked_domain }}', '{{ root_domain }}'],
                [$nakedDomain, $rootDomain],
                $nakedStub
            );

            $localNaked = "/tmp/nginx_{$nakedDomain}.conf";
            file_put_contents($localNaked, $nakedConfig);
            $remoteNaked = "/tmp/{$nakedDomain}.conf";
            $ssh->upload($localNaked, $remoteNaked);

            $commands[] = "sudo mv {$remoteNaked} /etc/nginx/sites-available/{$nakedDomain}";
            $commands[] = "sudo ln -sf /etc/nginx/sites-available/{$nakedDomain} /etc/nginx/sites-enabled/{$nakedDomain}";

            unlink($localNaked);
        }

        // Reload Nginx and finalize
        $commands[] = 'sudo nginx -t && sudo systemctl reload nginx';
        $commands[] = "[ -f {$deployBase}/current/artisan ] && cd {$deployBase}/current && php artisan key:generate";
        $commands[] = "[ -f {$deployBase}/current/artisan ] && cd {$deployBase}/current && php artisan config:clear";

        $ssh->run(implode(' && ', $commands));

        unlink($localTmp);

        outro("✅ Site '{$rootDomain}' created and Nginx configured.");

        return Command::SUCCESS;
    }
}
