<?php

namespace ThundrLabs\ThundrCli\Support\Traits;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ThundrLabs\ThundrCli\Support\ConfigManager;

use function Laravel\Prompts\select;

trait HandlesEnvironmentSelection
{
    protected function configureEnvironmentOption(): void
    {
        $this->addOption('env', 'e', InputOption::VALUE_REQUIRED, 'Which environment config to use (optional)');
    }

    protected function resolveEnvironment(InputInterface $input, OutputInterface $output): string
    {
        $projectConfig = ConfigManager::loadRawProjectConfig();
        $envs = array_keys($projectConfig);

        $inputEnv = $input->getOption('env');

        if ($inputEnv) {
            if (! in_array($inputEnv, $envs)) {
                throw new \RuntimeException("❌ Environment '{$inputEnv}' not found in thundr.yml.");
            }

            return $inputEnv;
        }

        if (count($envs) === 0) {
            throw new \RuntimeException('❌ No environments found in thundr.yml.');
        }

        if (count($envs) === 1) {
            return $envs[0];
        }

        return select(
            label: 'Which environment do you want to use?',
            options: $envs,
            scroll: 10
        );
    }
}
