<?php

namespace Mccomaschris\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\error;

#[AsCommand(name: 'init', description: 'Initialize a new thundr.yml file for this project')]
class InitCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = getcwd() . '/thundr.yml';
        $mainConfigPath = $_SERVER['HOME'] . '/.thundr/config.yml';

        if (file_exists($configPath)) {
            error("❌ thundr.yml already exists in this directory.");
            return Command::FAILURE;
        }

        if (!file_exists($mainConfigPath)) {
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
        outro("✅ thundr.yml created successfully.");

        return Command::SUCCESS;
    }
}
