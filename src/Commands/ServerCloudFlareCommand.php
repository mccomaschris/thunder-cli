<?php

namespace ThundrLabs\ThundrCli\Commands;

use ThundrLabs\ThundrCli\Support\ConfigManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[AsCommand(name: 'server:cloudflare', description: 'Add or update Cloudflare account info in ~/.thundr/config.yml')]
class ServerCloudFlareCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $config = ConfigManager::loadGlobalConfig();
        } catch (\RuntimeException $e) {
            $config = ['servers' => []];
        }

        if (isset($config['cloudflare']['api_token'])) {
            $confirmOverwrite = confirm('Cloudflare credentials already exist. Overwrite?', default: false);
            if (! $confirmOverwrite) {
                warning('Aborted.');

                return Command::SUCCESS;
            }
        }

        $apiToken = text('Cloudflare API token:');
        $accountId = text('Cloudflare Account ID (optional):', default: '');

        $config['cloudflare'] = [
            'api_token' => $apiToken,
            'account_id' => $accountId ?: null,
        ];

        outro('✅ Cloudflare credentials saved.');

        ConfigManager::saveGlobalConfig($config);

        return Command::SUCCESS;
    }
}
