<?php

namespace Mccomaschris\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

use function Laravel\Prompts\error;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;

#[AsCommand(name: 'server:provision', description: 'Add or update a server or Cloudflare API in ~/.thundr/config.yml')]
class ServerProvisionCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = ($_SERVER['HOME'] ?? getenv('HOME') ?: getenv('USERPROFILE')).'/.thundr/config.yml';

        if (! file_exists($configPath)) {
            error('❌ Missing ~/.thundr/config.yml');

            return Command::FAILURE;
        }

        $global = Yaml::parseFile($configPath);

        $servers = $global['servers'] ?? [];

        if (empty($servers)) {
            error('❌ No servers found in your Thundr config.');

            return Command::FAILURE;
        }

        $serverKey = select(
            label: 'Which server would you like to provision?',
            options: array_keys($servers)
        );

        $server = $servers[$serverKey];
        $user = $server['user'] ?? 'thundr';
        $host = $server['host'];
        $sshKey = $server['ssh_key'] ?? null;
        $sshOptions = $sshKey ? "-i {$sshKey}" : '';

        // Upload the provisioning script
        $localScript = dirname(__DIR__, 2).'/resources/scripts/provision.sh';
        $remotePath = '/tmp/thundr-provision.sh';

        if (! file_exists($localScript)) {
            error('❌ Missing provision.sh script in resources/scripts.');

            return Command::FAILURE;
        }

        $uploadCmd = "scp {$sshOptions} {$localScript} root@{$host}:{$remotePath}";
        $scpProcess = Process::fromShellCommandline($uploadCmd);
        $scpProcess->run();

        if (! $scpProcess->isSuccessful()) {
            error('❌ Failed to upload provision.sh to server.');

            return Command::FAILURE;
        }

        // Run the script remotely
        $sshCmd = "ssh {$sshOptions} root@{$host} 'sudo bash {$remotePath}'";
        $provision = Process::fromShellCommandline($sshCmd);
        $provision->setTimeout(600);
        $provision->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (! $provision->isSuccessful()) {
            error('❌ Provisioning failed.');

            return Command::FAILURE;
        }

        outro("✅ Server '{$serverKey}' provisioned successfully.");

        return Command::SUCCESS;
    }
}
