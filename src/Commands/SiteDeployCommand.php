<?php

namespace Mccomaschris\ThundrCli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;

#[AsCommand(name: 'site:deploy', description: 'Deploy a Laravel or Statamic app', aliases: ['deploy'])]
class SiteDeployCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cwd = getcwd();
        $projectYaml = $cwd . '/thundr.yml';
        $globalYaml = ($_SERVER['HOME'] ?? getenv('HOME') ?: getenv('USERPROFILE')) . '/.thundr/config.yml';

        if (!file_exists($projectYaml) || !file_exists($globalYaml)) {
            error("❌ Missing thundr.yml or ~/.thundr/config.yml");
            return Command::FAILURE;
        }

        $gitCheck = Process::fromShellCommandline('git status --porcelain');
        $gitCheck->run();

        if (!$gitCheck->isSuccessful()) {
            error("❌ Failed to run 'git status'. Are you in a Git repository?");
            return Command::FAILURE;
        }

        if (trim($gitCheck->getOutput()) !== '') {
            error("❌ Git working directory is not clean. Please commit or stash changes before deploying.");
            return Command::FAILURE;
        }

        $project = Yaml::parseFile($projectYaml);
        $global = Yaml::parseFile($globalYaml);

        $rootDomain = $project['root_domain'];
        $repo = $project['repo'];
        $branch = $project['branch'] ?? 'main';
        $phpVersion = $project['php_version'] ?? '8.3';
        $projectType = strtolower($project['project_type'] ?? 'laravel');
        $serverKey = $project['server'];
        $server = $global['servers'][$serverKey] ?? null;

        if (!$server) {
            error("❌ Server '{$serverKey}' not found in ~/.thundr/config.yml");
            return Command::FAILURE;
        }

        $user = $server['user'] ?? 'thundr';
        $host = $server['host'];
        $sshKey = $server['ssh_key'] ?? null;
        $sshOptions = $sshKey ? "-i {$sshKey}" : '';

        $deployBase = "/var/www/html/{$rootDomain}";
        $releasesDir = "$deployBase/releases";
        $currentDir = "$deployBase/current";
        $sharedEnv = "$deployBase/shared/.env";
        $timestamp = date('YmdHis');
        $newRelease = "$releasesDir/$timestamp";

        info("🔗 Starting zero-downtime deployment for {$rootDomain}...");

        // Check if database is ready for migrations
        $checkMigrate = "cd {$newRelease} && php artisan migrate:status";
        $sshCheck = Process::fromShellCommandline("ssh {$sshOptions} {$user}@{$host} '{$checkMigrate}'");
        $sshCheck->run();
        $shouldMigrate = $sshCheck->isSuccessful();

        $commands = [
            "sudo mkdir -p {$deployBase}",
            "sudo chown -R {$user}:www-data {$deployBase}",
            "sudo -u {$user} mkdir -p {$releasesDir}",
            "sudo -u {$user} mkdir -p {$deployBase}/shared",
            "sudo -u {$user} mkdir {$newRelease}",
            "cd {$newRelease}",
            "sudo -u {$user} git clone git@github.com:{$repo} .",
            "sudo -u {$user} git checkout {$branch}",
            "sudo -u {$user} git config --global --add safe.directory {$newRelease}",
            "sudo -u {$user} git fetch origin",
            "sudo -u {$user} git pull origin {$branch}",
            "[ -f {$sharedEnv} ] || cp {$newRelease}/.env.example {$sharedEnv}",
            "ln -sf {$sharedEnv} {$newRelease}/.env",
            "cd {$newRelease}",
            "sudo -u {$user} /usr/local/bin/composer install --no-dev --optimize-autoloader --no-ansi --no-progress --no-interaction",
            "sudo -u {$user} php artisan migrate --force",
            "sudo -u {$user} bash -c \"[ -f /home/{$user}/.nvm/nvm.sh ] && . /home/{$user}/.nvm/nvm.sh && cd {$newRelease} && npm ci && npm run build --silent\"",
            "sudo -u {$user} php artisan optimize:clear",
        ];

        if ($projectType === 'statamic') {
            $commands[] = "cd {$newRelease}";
            $commands[] = "sudo -u {$user} php artisan optimize:clear";
            $commands[] = "sudo -u {$user} php artisan optimize";
            $commands[] = "sudo -u {$user} php please stache:warm";
        }

        $initialScript = implode(" && ", $commands);
        $initialSSH = "ssh {$sshOptions} {$user}@{$host} '{$initialScript}'";

        $process = Process::fromShellCommandline($initialSSH);
        $process->setTimeout(300);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            error("❌ Deployment failed before switching symlink. No changes made to live site.");
            return Command::FAILURE;
        }

        $finalScript = implode(" && ", [
            // Set up symlinks
            "rm -rf {$newRelease}/storage",
            "ln -s {$deployBase}/shared/storage {$newRelease}/storage",

            "rm -rf {$newRelease}/public/uploads",
            "ln -s {$deployBase}/shared/public/uploads {$newRelease}/public/uploads",

            // Ensure required Laravel storage paths exist
            "mkdir -p {$newRelease}/storage/framework/cache",
            "mkdir -p {$newRelease}/storage/framework/sessions",
            "mkdir -p {$newRelease}/storage/framework/views",
            "mkdir -p {$newRelease}/storage/logs",

            // Symlink the new release as "current"
            "sudo ln -nsf {$newRelease} {$currentDir}",

            // Set permissions and ownership
            "sudo chown -R {$user}:www-data {$newRelease}",
            "sudo chmod -R 750 {$newRelease}",
            "sudo chown -R {$user}:www-data {$newRelease}/storage",
            "sudo chmod -R 775 {$newRelease}/storage",

            // Public storage symlink
            "rm -rf {$newRelease}/public/storage",
            "ln -s {$newRelease}/storage/app/public {$newRelease}/public/storage",

            // Reload PHP and Nginx
            "sudo systemctl reload php{$phpVersion}-fpm && sudo systemctl reload nginx",

            // Prune old releases (keep 5 most recent)
            "cd {$releasesDir} && [ -d . ] && ls -1t | tail -n +6 | xargs -I{} rm -rf {}"
        ]);

        $finalSSH = "ssh {$sshOptions} {$user}@{$host} '{$finalScript}'";
        $finalProcess = Process::fromShellCommandline($finalSSH);
        $finalProcess->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (!$finalProcess->isSuccessful()) {
            error("❌ Final symlink or service reload failed. Site might still be running the previous release.");
            return Command::FAILURE;
        }

        outro("✅ Deployment complete! New release deployed at: {$newRelease}");
        return Command::SUCCESS;
    }
}
