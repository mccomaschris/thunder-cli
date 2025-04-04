<?php

namespace ThundrLabs\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ThundrLabs\ThundrCli\Support\ConfigManager;
use ThundrLabs\ThundrCli\Support\RemoteSshRunner;
use ThundrLabs\ThundrCli\Support\Traits\HandlesEnvironmentSelection;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand(name: 'site:env', description: 'Manage environment variables on the remote server')]
class SiteEnvCommand extends Command
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
            error("❌ Server '{$serverKey}' not found in global config.");

            return Command::FAILURE;
        }

        $ssh = RemoteSshRunner::make($server);
        $envPath = "/var/www/html/{$rootDomain}/shared/.env";

        while (true) {
            $choice = select(
                'What would you like to do?',
                ['View all environment variables', 'Add a new variable', 'Edit a variable', 'Delete a variable', 'Exit'],
                scroll: 15
            );

            if ($choice === 'Exit') {
                info('👋 Goodbye!');
                break;
            }

            if ($choice === 'View all environment variables') {
                $all = $ssh->run("cat {$envPath}");
                if ($all === false) {
                    error('❌ Failed to read .env file.');
                } else {
                    info($all);
                }

                continue;
            }

            $allEnv = $ssh->run("cat {$envPath}");
            if ($allEnv === false) {
                error('❌ Failed to read remote .env file.');

                continue;
            }

            $lines = explode("\n", trim($allEnv));
            $envVars = [];
            foreach ($lines as $line) {
                if (str_contains($line, '=')) {
                    [$k, $v] = explode('=', $line, 2);
                    $envVars[trim($k)] = trim($v);
                }
            }

            do {
                if ($choice === 'Add a new variable') {
                    $key = text('Enter the key');
                    if (array_key_exists($key, $envVars)) {
                        error("❌ {$key} already exists.");
                        break;
                    }
                    $value = text("Enter the value for {$key}");
                    if (confirm("Add {$key}={$value} to the .env file?")) {
                        $append = $ssh->runWithStatus(<<<BASH
                        tee -a {$envPath} > /dev/null <<'EOF'
                        {$key}={$value}
                        EOF
                        BASH);

                        if (! $append['success']) {
                            error('❌ Failed to append to .env.');
                        } else {
                            info('✅ Added.');
                            $envVars[$key] = $value;
                        }
                    }
                }

                if ($choice === 'Edit a variable') {
                    if (empty($envVars)) {
                        error('❌ No variables to edit.');
                        break;
                    }
                    $key = select('Choose a variable to edit', array_keys($envVars), scroll: 15);
                    $current = $envVars[$key];
                    $new = text("New value for {$key}", default: $current);
                    if ($new !== $current && confirm("Replace {$key}={$current} with {$key}={$new}?")) {
                        $ok = $ssh->runWithStatus("sudo sed -i.bak \"/^{$key}=/c\\{$key}={$new}\" {$envPath}");
                        if ($ok['success']) {
                            info('✅ Updated.');
                            $envVars[$key] = $new;
                        } else {
                            error('❌ Failed to update .env.');
                        }
                    }
                }

                if ($choice === 'Delete a variable') {
                    if (empty($envVars)) {
                        error('❌ No variables to delete.');
                        break;
                    }
                    $key = select('Choose a variable to delete', array_keys($envVars), scroll: 15);
                    if (confirm("Are you sure you want to delete {$key}?")) {
                        $ok = $ssh->runWithStatus("sudo sed -i.bak \"/^{$key}=/d\" {$envPath}");
                        if ($ok['success']) {
                            info('✅ Deleted.');
                            unset($envVars[$key]);
                        } else {
                            error('❌ Failed to delete variable.');
                        }
                    }
                }
            } while (confirm("Would you like to {$this->actionName($choice)} another variable?", default: false));
        }

        return Command::SUCCESS;
    }

    private function actionName(string $choice): string
    {
        return match ($choice) {
            'Add a new variable' => 'add',
            'Edit a variable' => 'edit',
            'Delete a variable' => 'delete',
            default => 'change',
        };
    }
}
