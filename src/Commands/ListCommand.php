<?php

namespace Mccomaschris\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

#[AsCommand(name: 'list', description: 'List available servers and project details')]
class ListCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $_SERVER['HOME'] . '/.thundr/config.yml';
        $projectPath = getcwd() . '/thundr.yml';

        info("Global Servers:");
        if (!file_exists($configPath)) {
            warning("No global config found at ~/.thundr/config.yml");
            info("You can create one by running `thundr init`.");
        } else {
            $config = Yaml::parseFile($configPath);
            if (!empty($config['servers'])) {
                foreach ($config['servers'] as $key => $server) {
                    info("- <info>{$key}</info>: {$server['user']}@{$server['host']} (key: {$server['ssh_key']})");
                }
            } else {
                info("No servers configured yet..");
            }
        }

        // $output->writeln("\n<info>Project Config:</info>");
        // if (!file_exists($projectPath)) {
        //     warning("No thundr.yml found in this directory.");
        // } else {
        //     $project = Yaml::parseFile($projectPath);
        //     foreach ($project as $key => $value) {
        //         info("- {$key}: " . (is_bool($value) ? ($value ? 'true' : 'false') : $value));
        //     }
        // }

        return Command::SUCCESS;
    }
}
