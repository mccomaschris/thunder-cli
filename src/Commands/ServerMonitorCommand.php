<?php

namespace ThundrLabs\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ThundrLabs\ThundrCli\Support\ConfigManager;
use ThundrLabs\ThundrCli\Support\RemoteSshRunner;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;

#[AsCommand(name: 'monitor:status', description: 'Display server health and usage info')]
class ServerMonitorCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $globalConfig = ConfigManager::loadGlobalConfig();
        $servers = $globalConfig['servers'] ?? [];

        if (empty($servers)) {
            error('❌ No servers defined in global config.');

            return Command::FAILURE;
        }

        $selectedServer = select(
            label: 'Which server would you like to monitor?',
            options: array_keys($servers)
        );

        $server = $servers[$selectedServer] ?? null;

        if (! $server) {
            error("❌ Server config for '{$selectedServer}' not found.");

            return Command::FAILURE;
        }

        $ssh = RemoteSshRunner::make($server);

        info('🔧 Checking system status...');

        $uptime = $ssh->run('uptime');
        $loadavg = trim(explode('load average:', $uptime)[1] ?? '');

        $memoryRaw = $ssh->run('free -m | grep Mem');
        preg_match_all('/\d+/', $memoryRaw, $mem);

        [$total, $used, $free] = $mem[0];

        $diskRaw = $ssh->run('df -h / | tail -n 1');
        $diskParts = preg_split('/\s+/', $diskRaw);
        [$size, $usedDisk, $avail, $usePercent] = array_slice($diskParts, 1, 4);

        $os = isset($globalConfig['os']) ? $globalConfig['os'] : 'ubuntu';
        if ($os === 'oracle') {
            $phpService = 'php-fpm';
        } else {
            $phpService = 'php8.3-fpm';
        }
        $phpStatus = $ssh->run("systemctl is-active $phpService");

        info('🧠 Memory');
        table(['Total (MB)', 'Used', 'Free'], [[$total, $used, $free]]);

        info('💾 Disk');
        table(['Size', 'Used', 'Available', 'Usage %'], [[$size, $usedDisk, $avail, $usePercent]]);

        info('📊 Load Average');
        info($loadavg);

        info('🐘 PHP-FPM');
        info(trim($phpStatus));

        return Command::SUCCESS;
    }
}
