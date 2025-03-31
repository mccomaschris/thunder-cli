<?php

namespace Mccomaschris\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;

#[AsCommand(name: 'site:ssl', description: 'Add SSL (Cloudflare or Let\'s Encrypt) to site and configure nginx')]
class SiteSslCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cwd = getcwd();
        $projectYaml = $cwd . '/thundr.yml';
        $globalYaml = ($_SERVER['HOME'] ?? getenv('HOME') ?: getenv('USERPROFILE')) . '/.thundr/config.yml';

        if (!file_exists($projectYaml) || !file_exists($globalYaml)) {
            error("❌ Required configuration not found.");
            return Command::FAILURE;
        }

        $project = Yaml::parseFile($projectYaml);
        $global = Yaml::parseFile($globalYaml);

        $serverKey = $project['server'] ?? null;
        $server = $global['servers'][$serverKey] ?? null;
        $cloudflare = $global['cloudflare']['api_token'] ?? null;
        $letsencryptEmail = $global['letsencrypt']['email'] ?? null;

        if (!$server) {
            error("❌ Server not found in global config.");
            return Command::FAILURE;
        }

        $rootDomain = $project['root_domain'];
        $phpVersion = $project['php_version'] ?? '8.3';
        $os = strtolower($project['operating_system'] ?? 'ubuntu');
        $phpSocket = $os === 'oracle' ? 'php-fpm.sock' : "php{$phpVersion}-fpm.sock";

        $user = $server['user'];
        $host = $server['host'];
        $sshKey = $server['ssh_key'] ?? null;
        $sshOptions = $sshKey ? "-i {$sshKey}" : '';

        $sslType = select('Choose SSL type:', ['Cloudflare', "Let's Encrypt"], default: 'Cloudflare');

        // Check if certificate already exists on the server
        $checkCmd = $sslType === 'Cloudflare'
            ? "[ -f /etc/ssl/cloudflare/{$rootDomain}.pem ] && echo 'exists'"
            : "[ -f /etc/letsencrypt/live/{$rootDomain}/fullchain.pem ] && echo 'exists'";

        $checkCmdEscaped = escapeshellarg($checkCmd);
        $checkProcess = Process::fromShellCommandline("ssh {$sshOptions} {$user}@{$host} {$checkCmdEscaped}");
        $checkProcess->run();
        $exists = trim($checkProcess->getOutput()) === 'exists';

        if ($exists) {
            $overwrite = confirm("⚠️ A certificate already exists. Replace it?", false);
            if (!$overwrite) {
                info("☑️ Skipped SSL setup.");
                return Command::SUCCESS;
            }
        }

        if ($sslType === 'Cloudflare') {
            if (!$cloudflare) {
                error("❌ Cloudflare credentials missing in global config.");
                return Command::FAILURE;
            }

            info("🔐 Generating private key and CSR on remote server...");
            $csrCmd = "openssl req -new -newkey rsa:2048 -nodes -keyout /tmp/{$rootDomain}.key -out /tmp/{$rootDomain}.csr -subj \"/CN={$rootDomain}\"";
            $csrProcess = Process::fromShellCommandline("ssh {$sshOptions} {$user}@{$host} " . escapeshellarg($csrCmd));
            $csrProcess->run();

            if (!$csrProcess->isSuccessful()) {
                error("❌ Failed to generate CSR on remote server.");
                return Command::FAILURE;
            }

            info("⬇️ Downloading CSR...");
            $scpCsr = Process::fromShellCommandline("scp {$sshOptions} {$user}@{$host}:/tmp/{$rootDomain}.csr /tmp/{$rootDomain}.csr");
            $scpCsr->run();
            if (!$scpCsr->isSuccessful()) {
                error("❌ Failed to download CSR from server.");
                return Command::FAILURE;
            }

            $csr = file_get_contents("/tmp/{$rootDomain}.csr");

            info("📡 Requesting Cloudflare Origin Certificate...");
            $payload = json_encode([
                'hostnames' => [$rootDomain],
                'csr' => $csr,
                'request_type' => 'origin-rsa',
            ]);

            $ch = curl_init('https://api.cloudflare.com/client/v4/certificates');

            if (!$cloudflare) {
                error("❌ Cloudflare User Service Key is missing.");
                return Command::FAILURE;
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-Auth-User-Service-Key: ' . $cloudflare,
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
            ]);

            $response = curl_exec($ch);

            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($response, true);
            if ($status !== 200 || !$data['success']) {
                error("❌ Failed to get certificate from Cloudflare.");
                return Command::FAILURE;
            }

            $cert = $data['result']['certificate'];
            file_put_contents("/tmp/{$rootDomain}.pem", $cert);

            $uploadPem = Process::fromShellCommandline("scp {$sshOptions} /tmp/{$rootDomain}.pem {$user}@{$host}:/tmp/{$rootDomain}.pem");
            $uploadPem->run();

            if (!$uploadPem->isSuccessful()) {
                error("❌ Failed to upload .pem file to server.");
                return Command::FAILURE;
            }

            // Generate nginx config from stub
            $possiblePaths = [
                __DIR__ . '/../../../resources/stubs/nginx-ssl.stub', // local dev
                __DIR__ . '/../../resources/stubs/nginx-ssl.stub',    // global vendor install
            ];

            $stubPath = null;

            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $stubPath = realpath($path);
                    break;
                }
            }

            if (!$stubPath || !file_exists($stubPath)) {
                error("❌ Nginx stub not found. Looked in:\n" . implode("\n", $possiblePaths));
                return Command::FAILURE;
            }

            $stub = file_get_contents($stubPath);

            $nginxConf = str_replace(
                ['{{ root_domain }}', '{{ php_socket }}'],
                [$rootDomain, $phpSocket],
                $stub
            );

            file_put_contents("/tmp/nginx_{$rootDomain}.conf", $nginxConf);

            $uploadNginx = Process::fromShellCommandline("scp {$sshOptions} /tmp/nginx_{$rootDomain}.conf {$user}@{$host}:/tmp/{$rootDomain}-ssl.conf");
            $uploadNginx->run();

            if (!$uploadNginx->isSuccessful()) {
                error("❌ Failed to upload nginx config.");
                return Command::FAILURE;
            }

            $script = implode(" && ", [
                "sudo mkdir -p /etc/ssl/cloudflare",
                "sudo mv /tmp/{$rootDomain}.pem /etc/ssl/cloudflare/{$rootDomain}.pem",
                "sudo mv /tmp/{$rootDomain}.key /etc/ssl/cloudflare/{$rootDomain}.key",
                "sudo mv /tmp/{$rootDomain}-ssl.conf /etc/nginx/sites-available/{$rootDomain}",
                "sudo ln -sf /etc/nginx/sites-available/{$rootDomain} /etc/nginx/sites-enabled/{$rootDomain}",
                "sudo nginx -t && sudo systemctl reload nginx"
            ]);

        } else {
            if (!$letsencryptEmail) {
                error("❌ Missing Let's Encrypt admin email in global config.");
                return Command::FAILURE;
            }

            $script = "sudo certbot --nginx -d {rootDomain} --non-interactive --agree-tos --email {letsencryptEmail}";
        }

        $sshCmd = "ssh {$sshOptions} {$user}@{$host} '{$script}'";
        $process = Process::fromShellCommandline($sshCmd);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            error("❌ SSL setup failed.");
            return Command::FAILURE;
        }

        outro("✅ SSL installed for {$rootDomain} using {$sslType}.");
        return Command::SUCCESS;
    }
}
