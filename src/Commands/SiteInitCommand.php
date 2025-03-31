<?php

namespace Mccomaschris\ThundrCli\Commands;

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
        $cwd = getcwd();
        $configPath = $cwd.'/thundr.yml';
        $mainConfigPath = $_SERVER['HOME'].'/.thundr/config.yml';

        if (file_exists($configPath)) {
            error('❌ thundr.yml already exists in this directory.');

            return Command::FAILURE;
        }

        if (! file_exists($mainConfigPath)) {
            error("❌ No ~/.thundr/config.yml found. Please run 'thundr config' first.");

            return Command::FAILURE;
        }

        $globalConfig = Yaml::parseFile($mainConfigPath);
        $servers = $globalConfig['servers'] ?? [];

        if (empty($servers)) {
            error("❌ No servers found in ~/.thundr/config.yml. Add one with 'thundr config'.");

            return Command::FAILURE;
        }

        $rootDomain = text('Root domain (e.g. example.com):');
        $repo = text('GitHub repo (e.g. user/repo):');
        $branch = text('Branch to deploy:', default: 'main');
        $phpVersion = text('PHP version:', default: '8.3');
        $projectType = strtolower(select('Project type:', ['Laravel', 'Statamic']));
        $server = select('Which server should this project deploy to?', array_keys($servers));
        $operatingSystem = select('Operating System:', ['Ubuntu', 'Oracle']);

        $config = [
            'root_domain' => $rootDomain,
            'repo' => $repo,
            'branch' => $branch,
            'php_version' => $phpVersion,
            'project_type' => $projectType,
            'server' => $server,
            'operating_system' => strtolower($operatingSystem),
        ];

        file_put_contents($configPath, Yaml::dump($config, 4, 2));

        $gitignorePath = $cwd.'/.gitignore';

        if (file_exists($gitignorePath)) {
            $lines = file($gitignorePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $suggested = [
                '/storage/framework/thundr-files/',
                '/storage/logs/',
            ];

            $missing = array_filter($suggested, fn ($entry) => ! in_array($entry, $lines));

            if (! empty($missing)) {
                if (confirm('Add recommended Thundr entries to .gitignore?')) {
                    file_put_contents($gitignorePath, PHP_EOL.implode(PHP_EOL, $missing).PHP_EOL, FILE_APPEND);
                    info('✅ Added entries to .gitignore.');
                }
            }
        }

        outro('✅ thundr.yml created successfully.');

        return Command::SUCCESS;
    }
}
