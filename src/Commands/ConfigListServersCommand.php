<?php

namespace Mccomaschris\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\table;

#[AsCommand(name: 'config:servers', description: 'List available servers and project details')]
class ConfigListServersCommand extends Command
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
                table(
                    headers: ['Server', 'User', 'Host', 'SSH Key'],
                    rows: array_map(function ($key, $server) {
                        return [
                            $key,
                            $server['user'],
                            $server['host'],
                            $server['ssh_key'] ?? 'N/A',
                        ];
                    }, array_keys($config['servers']), $config['servers']),
                );
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
