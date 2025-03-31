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
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand(name: 'site:env', description: 'Manage environment variables on the remote server')]
class SiteEnvCommand extends Command
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
        $envPath = "/var/www/html/{$rootDomain}/shared/.env";

        $choice = select(
            'What would you like to do?',
            ['View all environment variables', 'Add a new variable', 'Edit a variable', 'Delete a variable'],
            scroll: 15
        );

        if ($choice === 'View all environment variables') {
            $cmd = "ssh {$sshOptions} {$user}@{$host} 'cat {$envPath}'";
            $process = Process::fromShellCommandline($cmd);
            $process->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });

            return Command::SUCCESS;
        }

        // Load current .env contents
        $cmd = "ssh {$sshOptions} {$user}@{$host} 'cat {$envPath}'";
        $process = Process::fromShellCommandline($cmd);
        $process->run();
        if (! $process->isSuccessful()) {
            error('❌ Failed to read remote .env file.');

            return Command::FAILURE;
        }

        $lines = explode('
', trim($process->getOutput()));
        $envVars = [];
        foreach ($lines as $line) {
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $envVars[trim($k)] = trim($v);
            }
        }

        if ($choice === 'Add a new variable') {
            $key = text('Enter the key');
            if (array_key_exists($key, $envVars)) {
                error("❌ {$key} already exists.");

                return Command::FAILURE;
            }
            $value = text("Enter the value for {$key}");
            if (confirm("Add {$key}={$value} to the .env file?")) {
                $append = "echo \"{$key}={$value}\" | ssh {$sshOptions} {$user}@{$host} 'cat >> {$envPath}'";
                Process::fromShellCommandline($append)->run();
                info('✅ Added.');
            }
        }

        if ($choice === 'Edit a variable') {
            $key = select('Choose a variable to edit', array_keys($envVars), scroll: 15);
            $current = $envVars[$key];
            $new = text("New value for {$key}", default: $current);
            if ($new !== $current && confirm("Replace {$key}={$current} with {$key}={$new}?")) {
                $escapedKey = escapeshellarg($key);
                $escapedNew = escapeshellarg("{$key}={$new}");
                $cmd = "ssh {$sshOptions} {$user}@{$host} 'sed -i.bak \"/^{$key}=/c\\{$key}={$new}\" {$envPath}'";
                Process::fromShellCommandline($cmd)->run();
                info('✅ Updated.');
            }
        }

        if ($choice === 'Delete a variable') {
            $key = select('Choose a variable to delete', array_keys($envVars), scroll: 15);
            if (confirm("Are you sure you want to delete {$key}?")) {
                $cmd = "ssh {$sshOptions} {$user}@{$host} 'sed -i.bak \"/^{$key}=/d\" {$envPath}'";
                Process::fromShellCommandline($cmd)->run();
                info('✅ Deleted.');
            }
        }

        return Command::SUCCESS;
    }
}
