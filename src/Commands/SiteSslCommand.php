<?php

namespace ThundrLabs\ThundrCli\Commands;

use ThundrLabs\ThundrCli\Support\ConfigManager;
use ThundrLabs\ThundrCli\Support\RemoteSshRunner;
use ThundrLabs\ThundrCli\Support\Traits\HandlesEnvironmentSelection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;

#[AsCommand(name: 'site:ssl', description: 'Add SSL (Cloudflare or Let\'s Encrypt) to site and configure nginx')]
class SiteSslCommand extends Command
{
    use HandlesEnvironmentSelection;

    protected function configure(): void
    {
        $this->configureEnvironmentOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $env = $this->resolveEnvironment($input, $output);
            $project = ConfigManager::loadProjectConfig($env);
            $global = ConfigManager::loadGlobalConfig();
        } catch (\RuntimeException $e) {
            error('❌ '.$e->getMessage());

            return Command::FAILURE;
        }

        $rootDomain = $project['root_domain'];
        $serverKey = $project['server'] ?? null;
        $nakedRedirect = $project['naked_redirect'] ?? false;
        $server = $global['servers'][$serverKey] ?? null;
        $cloudflare = $global['cloudflare']['api_token'] ?? null;
        $letsencryptEmail = $global['letsencrypt']['email'] ?? null;

        if (! $server) {
            error('❌ Server not found in global config.');

            return Command::FAILURE;
        }

        $ssh = RemoteSshRunner::make($server);

        $sslType = select('Choose SSL type:', ['Cloudflare', "Let's Encrypt"], default: 'Cloudflare');

        $checkCmd = $sslType === 'Cloudflare'
            ? "[ -f /etc/ssl/cloudflare/{$rootDomain}.pem ] && echo 'exists'"
            : "[ -f /etc/letsencrypt/live/{$rootDomain}/fullchain.pem ] && echo 'exists'";

        $exists = trim($ssh->run($checkCmd)) === 'exists';

        if ($exists) {
            $overwrite = confirm('⚠️ A certificate already exists. Replace it?', false);
            if (! $overwrite) {
                info('☑️ Skipped SSL setup.');

                return Command::SUCCESS;
            }

            if ($sslType === 'Cloudflare') {
                $certId = trim($ssh->run("cat /etc/ssl/cloudflare/{$rootDomain}.cert_id"));
                if (empty($certId)) {
                    error('❌ Failed to retrieve existing certificate ID.');

                    return Command::FAILURE;
                }

                info("🔓 Revoking certificate ID {$certId} via Cloudflare...");

                $ch = curl_init("https://api.cloudflare.com/client/v4/certificates/{$certId}");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => 'DELETE',
                    CURLOPT_HTTPHEADER => [
                        'X-Auth-User-Service-Key: '.$cloudflare,
                    ],
                ]);
                $response = curl_exec($ch);
                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($status !== 200) {
                    error('❌ Failed to revoke certificate on Cloudflare.');

                    return Command::FAILURE;
                }

                $ssh->run(implode(' && ', [
                    "sudo rm -f /etc/ssl/cloudflare/{$rootDomain}.pem",
                    "sudo rm -f /etc/ssl/cloudflare/{$rootDomain}.key",
                    "sudo rm -f /etc/ssl/cloudflare/{$rootDomain}.cert_id",
                ]));
            }
        }

        if ($sslType === 'Cloudflare') {
            if (! $cloudflare) {
                error('❌ Cloudflare API token is missing.');

                return Command::FAILURE;
            }

            info('📁 Ensuring /etc/ssl/cloudflare exists...');
            $ssh->run('sudo mkdir -p /etc/ssl/cloudflare');

            info('🔐 Generating private key and CSR on remote server...');
            $ssh->run("openssl req -new -newkey rsa:2048 -nodes -keyout /tmp/{$rootDomain}.key -out /tmp/{$rootDomain}.csr -subj \"/CN={$rootDomain}\"");

            $keyExists = $ssh->run("[ -f /tmp/{$rootDomain}.key ] && echo 'exists'");
            if (trim($keyExists) !== 'exists') {
                error("❌ Private key was not generated at /tmp/{$rootDomain}.key");

                return Command::FAILURE;
            }

            $csr = $ssh->run("cat /tmp/{$rootDomain}.csr");

            $hostnames = [$rootDomain];

            // optionally add non-www version
            if (str_starts_with($rootDomain, 'www.')) {
                $hostnames[] = str_replace('www.', '', $rootDomain);
            } elseif (! str_starts_with($rootDomain, 'www.')) {
                $hostnames[] = 'www.'.$rootDomain;
            }

            info('📡 Requesting Cloudflare Origin Certificate...');
            $payload = json_encode([
                'hostnames' => array_unique($hostnames),
                'csr' => $csr,
                'request_type' => 'origin-rsa',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $ch = curl_init('https://api.cloudflare.com/client/v4/certificates');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-Auth-User-Service-Key: '.$cloudflare,
                    'Content-Type: application/json',
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
            ]);

            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($response, true);
            if ($status !== 200 || ! $data['success']) {
                error('❌ Failed to get certificate from Cloudflare.');

                return Command::FAILURE;
            }

            $cert = $data['result']['certificate'];
            $certId = $data['result']['id'];

            info('📝 Writing certificate and ID on remote server...');
            $ssh->run("echo {$certId} | sudo tee /etc/ssl/cloudflare/{$rootDomain}.cert_id > /dev/null");

            $tempPem = tempnam(sys_get_temp_dir(), "{$rootDomain}_cert_").'.pem';
            file_put_contents($tempPem, $cert);
            $ssh->upload($tempPem, "/tmp/{$rootDomain}.pem");
            @unlink($tempPem);

            $movePem = $ssh->runWithStatus("sudo mv /tmp/{$rootDomain}.pem /etc/ssl/cloudflare/{$rootDomain}.pem");
            if (! $movePem['success']) {
                error('❌ Failed to move certificate to remote server.');
                if (! empty($movePem['output'])) {
                    info("📤 Output:\n".$movePem['output']);
                }

                return Command::FAILURE;
            }

            $moveKey = $ssh->runWithStatus("sudo mv /tmp/{$rootDomain}.key /etc/ssl/cloudflare/{$rootDomain}.key");
            if (! $moveKey['success']) {
                error('❌ Failed to move  private key to /etc/ssl/cloudflare.');
                if (! empty($moveKey['output'])) {
                    info("📤 Output:\n".$moveKey['output']);
                }

                return Command::FAILURE;
            }

            // 🔍 Check if the current Nginx config already uses SSL
            $currentConf = trim($ssh->run("sudo cat /etc/nginx/sites-available/{$rootDomain} 2>/dev/null"));

            if (str_contains($currentConf, 'listen 443 ssl;')) {
                info('✅ Nginx config already supports SSL. Skipping config replacement.');
            } else {
                // 🔁 Replace Nginx config with SSL version
                $sslStubPaths = [
                    __DIR__.'/../../../resources/stubs/nginx-ssl.stub',
                    __DIR__.'/../../resources/stubs/nginx-ssl.stub',
                ];

                $stubPath = null;
                foreach ($sslStubPaths as $path) {
                    if (file_exists($path)) {
                        $stubPath = realpath($path);
                        break;
                    }
                }

                if (! $stubPath) {
                    error('❌ SSL Nginx stub not found.');

                    return Command::FAILURE;
                }

                $stub = file_get_contents($stubPath);
                $phpVersion = $project['php_version'] ?? '8.3';
                $phpSocket = "php{$phpVersion}-fpm.sock";
                $nginxConfig = str_replace(
                    ['{{ root_domain }}', '{{ php_version }}', '{{ php_socket }}'],
                    [$rootDomain, $phpVersion, $phpSocket],
                    $stub
                );

                $tempConf = tempnam(sys_get_temp_dir(), "{$rootDomain}_nginx_").'.conf';
                file_put_contents($tempConf, $nginxConfig);
                $ssh->upload($tempConf, "/tmp/{$rootDomain}.conf");
                @unlink($tempConf);

                $ssh->run("sudo mv /tmp/{$rootDomain}.conf /etc/nginx/sites-available/{$rootDomain}");
                $ssh->run("sudo ln -sf /etc/nginx/sites-available/{$rootDomain} /etc/nginx/sites-enabled/{$rootDomain}");

                if (str_starts_with($rootDomain, 'www.')) {
                    $nakedDomain = str_replace('www.', '', $rootDomain);
                } else {
                    $nakedDomain = null;
                }

                if ($nakedRedirect && isset($nakedDomain)) {
                    $nakedStubPaths = [
                        __DIR__.'/../../../resources/stubs/nginx-ssl-naked.stub',
                        __DIR__.'/../../resources/stubs/nginx-ssl-naked.stub',
                    ];

                    $nakedStubPath = null;
                    foreach ($nakedStubPaths as $path) {
                        if (file_exists($path)) {
                            $nakedStubPath = realpath($path);
                            break;
                        }
                    }

                    if (! $nakedStubPath) {
                        error("❌ Nginx naked domain redirect stub not found. Looked in:\n".implode("\n", $nakedStubPaths));

                        return Command::FAILURE;
                    }

                    $nakedStub = file_get_contents($nakedStubPath);
                    $nakedConfig = str_replace(
                        ['{{ naked_domain }}', '{{ root_domain }}'],
                        [$nakedDomain, $rootDomain],
                        $nakedStub
                    );

                    $localNakedSsl = "/tmp/nginx_{$nakedDomain}-ssl.conf";
                    file_put_contents($localNakedSsl, $nakedConfig);
                    $remoteNaked = "/tmp/{$nakedDomain}-ssl.conf";
                    $ssh->upload($localNakedSsl, $remoteNaked);

                    $commands[] = "sudo mv {$remoteNaked} /etc/nginx/sites-available/{$nakedDomain}-ssl";
                    $commands[] = "sudo ln -sf /etc/nginx/sites-available/{$nakedDomain} /etc/nginx/sites-enabled/{$nakedDomain}-ssl";

                    unlink($localNakedSsl);

                    $ssh->run(implode(' && ', $commands));
                }
            }

            $testNginx = $ssh->runWithStatus('sudo nginx -t');

            if (! $testNginx['success']) {
                error('❌ NGINX config test failed.');
                if (! empty($testNginx['output'])) {
                    info("📤 Output:\n".$testNginx['output']);
                }

                return Command::FAILURE;
            }

            $reloadNginx = $ssh->runWithStatus('sudo systemctl reload nginx');
            if (! $reloadNginx['success']) {
                error('❌ Failed to reload NGINX.');

                return Command::FAILURE;
            }

            outro("✅ SSL installed for {$rootDomain} using Cloudflare.");

            return Command::SUCCESS;
        }

        return Command::SUCCESS;
    }
}
