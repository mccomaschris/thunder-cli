<?php

namespace ThundrLabs\ThundrCli\Commands;

use ThundrLabs\ThundrCli\Support\ConfigManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand(name: 'site:init', description: 'Initialize a new thundr.yml file for this project')]
class SiteInitCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $global = ConfigManager::loadGlobalConfig();
        } catch (\RuntimeException $e) {
            error('❌ '.$e->getMessage());

            return Command::FAILURE;
        }

        $servers = $global['servers'] ?? [];

        if (empty($servers)) {
            error("❌ No servers found in ~/.thundr/config.yml. Add one with 'thundr server:create'.");

            return Command::FAILURE;
        }

        $configPath = getcwd().'/thundr.yml';
        $existingConfig = ConfigManager::loadRawProjectConfig();

        // Ask for environment name and ensure it's unique
        $newEnv = text('Enter a unique environment name for this project (e.g. production):', default: 'production');

        if (isset($existingConfig[$newEnv])) {
            error("❌ An environment named '{$newEnv}' already exists in thundr.yml.");

            return Command::FAILURE;
        }

        // Gather user input
        $rootDomain = text('Root domain (e.g. example.com):');

        $nakedRedirect = false;
        if (str_starts_with($rootDomain, 'www.')) {
            $nakedDomain = str_replace('www.', '', $rootDomain);
            $nakedRedirect = confirm("❓ Do you want to redirect naked domain ({$nakedDomain}) to www ({$rootDomain})?", default: true);
        }

        $repo = text('GitHub repo (e.g. user/repo):');
        $branch = text('Branch to deploy:', default: 'main');
        $phpVersion = text('PHP version:', default: '8.3');
        $projectType = strtolower(select('Project type:', ['Laravel', 'Statamic']));
        $server = select('Which server should this project deploy to?', array_keys($servers));

        // Add new env to config array
        $existingConfig[$newEnv] = [
            'root_domain' => $rootDomain,
            'repo' => $repo,
            'branch' => $branch,
            'php_version' => $phpVersion,
            'project_type' => $projectType,
            'server' => $server,
        ];

        if ($nakedRedirect) {
            $existingConfig[$newEnv]['naked_redirect'] = $nakedRedirect;
        }

        // Save updated config
        file_put_contents($configPath, Yaml::dump($existingConfig, 4, 2));

        // Optionally update .gitignore
        $gitignorePath = getcwd().'/.gitignore';

        if (file_exists($gitignorePath)) {
            $lines = file($gitignorePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $suggested = [
                '/storage/framework/thundr-files/',
                '/storage/logs/',
            ];

            $missing = array_filter($suggested, fn ($entry) => ! in_array($entry, $lines));

            if (! empty($missing) && confirm('Add recommended Thundr entries to .gitignore?')) {
                file_put_contents($gitignorePath, PHP_EOL.implode(PHP_EOL, $missing).PHP_EOL, FILE_APPEND);
                info('✅ Added entries to .gitignore.');
            }
        }

        outro("✅ thundr.yml updated with new environment: {$newEnv}");

        return Command::SUCCESS;
    }
}
