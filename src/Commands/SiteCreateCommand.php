<?php

namespace Mccomaschris\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

use function Laravel\Prompts\error;
use function Laravel\Prompts\outro;

#[AsCommand(name: 'site:create', description: 'Provision a new site on the remote server')]
class SiteCreateCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cwd = getcwd();
        $projectYaml = $cwd.'/thundr.yml';
        $globalYaml = ($_SERVER['HOME'] ?? getenv('HOME') ?: getenv('USERPROFILE')).'/.thundr/config.yml';

        if (! file_exists($projectYaml)) {
            error('❌ No thundr.yml found in this directory.');

            return Command::FAILURE;
        }

        if (! file_exists($globalYaml)) {
            error('❌ Missing ~/.thundr/config.yml');

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

        $rootDomain = $project['root_domain'];
        $phpVersion = $project['php_version'] ?? '8.3';
        $user = $server['user'] ?? 'thundr';
        $host = $server['host'];
        $sshKey = $server['ssh_key'] ?? null;
        $sshOptions = $sshKey ? "-i {$sshKey}" : '';

        $deployBase = "/var/www/html/{$rootDomain}";
        $sharedDirs = [
            "$deployBase/releases",
            "$deployBase/shared/storage",
            "$deployBase/shared/public/uploads",
        ];

        $commands = [];
        foreach ($sharedDirs as $dir) {
            $commands[] = "sudo mkdir -p {$dir}";
        }

        // Generate nginx config from stub
        $possiblePaths = [
            __DIR__.'/../../../resources/stubs/nginx.stub', // local dev
            __DIR__.'/../../resources/stubs/nginx.stub',    // global vendor install
        ];

        $stubPath = null;

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $stubPath = realpath($path);
                break;
            }
        }

        if (! $stubPath || ! file_exists($stubPath)) {
            error("❌ Nginx stub not found. Looked in:\n".implode("\n", $possiblePaths));

            return Command::FAILURE;
        }

        $stub = file_get_contents($stubPath);

        $serverType = strtolower($project['server_type'] ?? 'ubuntu');

        $phpSocket = match ($serverType) {
            'oracle' => 'php-fpm.sock',
            default => "php{$phpVersion}-fpm.sock"
        };

        $nginxConfig = str_replace(
            ['{{ root_domain }}', '{{ php_version }}', '{{ php_socket }}'],
            [$rootDomain, $phpVersion, $phpSocket],
            $stub
        );

        $tmpFile = "/tmp/{$rootDomain}.conf";

        file_put_contents("/tmp/nginx_{$rootDomain}.conf", $nginxConfig);

        $uploadCmd = "scp {$sshOptions} /tmp/nginx_{$rootDomain}.conf {$user}@{$host}:{$tmpFile}";

        $scpProcess = Process::fromShellCommandline($uploadCmd);
        $scpProcess->run();

        if (! $scpProcess->isSuccessful()) {
            error('❌ Failed to upload Nginx config via SCP.');

            return Command::FAILURE;
        }

        $commands[] = "sudo mv {$tmpFile} /etc/nginx/sites-available/{$rootDomain}";
        $commands[] = "sudo ln -sf /etc/nginx/sites-available/{$rootDomain} /etc/nginx/sites-enabled/{$rootDomain}";
        $commands[] = 'sudo nginx -t && sudo systemctl reload nginx';

        $commands[] = "[ -f {$deployBase}/current/artisan ] && cd {$deployBase}/current && sudo -u {$user} php artisan key:generate";

        // Build shell script
        $script = implode(' && ', $commands);

        // Run commands remotely
        $sshKey = $server['ssh_key'] ?? null;
        $sshOptions = $sshKey ? "-i {$sshKey}" : '';
        $sshCmd = "ssh {$sshOptions} {$user}@{$host} '{$script}'";
        $sshCmd = "ssh {$sshOptions} {$user}@{$host} '{$script}'";
        $process = Process::fromShellCommandline($sshCmd);
        $process->setTimeout(300);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        // Clean up local temp file
        unlink("/tmp/nginx_{$rootDomain}.conf");

        if (! $process->isSuccessful()) {
            error("❌ Failed to create site: {$rootDomain}");

            return Command::FAILURE;
        }

        outro("✅ Site '{$rootDomain}' created and Nginx configured.");

        return Command::SUCCESS;
    }
}
