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

#[AsCommand(name: 'site:deploy', description: 'Deploy a Laravel or Statamic app', aliases: ['deploy'])]
class SiteDeployCommand extends Command
{
    use HandlesEnvironmentSelection;

    protected function configure(): void
    {
        $this->configureEnvironmentOption();
        $this->addOption('debug', null, null, 'Run deployment in debug mode (execute each command step-by-step)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $debug = $input->getOption('debug');

        try {
            $env = $this->resolveEnvironment($input, $output);
            $project = ConfigManager::loadProjectConfig($env);
            $global = ConfigManager::loadGlobalConfig();
        } catch (\RuntimeException $e) {
            error('❌ '.$e->getMessage());

            return Command::FAILURE;
        }

        $rootDomain = $project['root_domain'];
        $repo = $project['repo'];
        $branch = $project['branch'] ?? 'main';
        $phpVersion = $project['php_version'] ?? '8.3';
        $retainReleases = $project['retain_releases'] ?? 5;
        $os = $global['servers'][$project['server']]['os'] ?? 'ubuntu';
        $webGroup = $os === 'oracle' ? 'nginx' : 'www-data';
        $projectType = strtolower($project['project_type'] ?? 'laravel');
        $database = $project['database'] ?? 'mysql';
        $serverKey = $project['server'] ?? null;
        $server = $global['servers'][$serverKey] ?? null;

        $phpRestart = $this->getPhpFpmService($os, $phpVersion);

        if (! $server) {
            error("❌ Server '{$serverKey}' not found in config.");

            return Command::FAILURE;
        }

        $ssh = RemoteSshRunner::make($server);
        $user = $server['user'] ?? 'thundr';
        $deployBase = "/var/www/html/{$rootDomain}";
        $releasesDir = "$deployBase/releases";
        $currentDir = "$deployBase/current";
        $sharedEnv = "$deployBase/shared/.env";
        $timestamp = date('YmdHis');
        $newRelease = "$releasesDir/$timestamp";

        $migrateDb = false;

        if ($database === 'sqlite') {
            // Check if the SQLite file exists on the server
            $dbFile = "{$deployBase}/shared/database/database.sqlite";
            $checkSqlite = trim($ssh->run("[ -f {$dbFile} ] && echo exists || echo missing"));

            if ($checkSqlite === 'exists') {
                $migrateDb = confirm('SQLite database exists. Do you want to run migrations?', default: false);
            } else {
                info('SQLite database file does not exist yet. Skipping migrations on first deploy.');
            }
        } else {
            // MySQL (or other) — use the normal prompt
            $migrateDb = confirm('Do you want to run migrations?', default: true);
        }

        info("🔗 Starting zero-downtime deployment for {$rootDomain}...");

        $commands = [
            "sudo mkdir -p {$deployBase}",
            "sudo chown -R {$user}:{$webGroup} {$deployBase}",
            "sudo -u {$user} mkdir -p {$releasesDir}",
            "sudo -u {$user} mkdir -p {$deployBase}/shared",
            "sudo -u {$user} mkdir {$newRelease}",

            // Git clone and setup
            // Clone just the default branch
            "sudo -u {$user} git clone --no-checkout git@github.com:{$repo} {$newRelease}",
            "sudo -u {$user} git -C {$newRelease} config --global --add safe.directory {$newRelease}",
            "sudo -u {$user} git -C {$newRelease} remote set-branches origin '*'",
            "sudo -u {$user} git -C {$newRelease} fetch origin",
            "sudo -u {$user} git -C {$newRelease} checkout {$branch}",
        ];

        if ($database === 'sqlite') {
            $commands[] = "echo 🔗 Linking SQLite: {$deployBase}/shared/database/database.sqlite -> {$newRelease}/database/database.sqlite";
            $commands[] = "sudo -u {$user} mkdir -p {$newRelease}/database";
            $commands[] = "sudo rm -f {$newRelease}/database/database.sqlite";
            $commands[] = "sudo -u {$user} ln -snf {$deployBase}/shared/database/database.sqlite {$newRelease}/database/database.sqlite";
            $commands[] = "ls -l {$newRelease}/database";
        }

        $commands = array_merge($commands, [
            "[ -f {$sharedEnv} ] || cp {$newRelease}/.env.example {$sharedEnv}",
            "ln -sf {$sharedEnv} {$newRelease}/.env",

            // Composer
            "sudo -u {$user} /usr/local/bin/composer --working-dir={$newRelease} install --no-dev --optimize-autoloader --no-ansi --no-progress --no-interaction",
        ]);

        $commands[] = "sudo -u {$user} bash -c \"[ -f /home/{$user}/.nvm/nvm.sh ] && . /home/{$user}/.nvm/nvm.sh && cd {$newRelease} && npm ci && npm run build --silent\"";

        if ($debug) {
            foreach ($commands as $index => $command) {
                $output->writeln("<info>[$index] Running:</info> $command");

                $result = $ssh->runWithStatus($command, timeout: 300); // per-command timeout

                $output->writeln($result['output']);

                if (! $result['success']) {
                    error("❌ Command failed at step [$index]: $command");

                    return Command::FAILURE;
                }
            }
        } else {
            $script = implode(' && ', $commands);
            $run = $ssh->runWithStatus($script, timeout: 600);
            $output->writeln($run['output']);

            if (! $run['success']) {
                error('❌ Deployment failed before switching symlink. No changes made to live site.');
                return Command::FAILURE;
            }
        }

        $final = [
            "rm -rf {$newRelease}/storage",
            "ln -s {$deployBase}/shared/storage {$newRelease}/storage",

            "rm -rf {$newRelease}/public/uploads",
            "ln -s {$deployBase}/shared/public/uploads {$newRelease}/public/uploads",

            "mkdir -p {$deployBase}/shared/storage/framework/cache",
            "mkdir -p {$deployBase}/shared/storage/framework/sessions",
            "mkdir -p {$deployBase}/shared/storage/framework/views",
            "mkdir -p {$deployBase}/shared/storage/logs",

            // ✅ Now switch the symlink
            "sudo ln -nsf {$newRelease} {$currentDir}",

            "cd {$currentDir} && php artisan migrate:status || echo '⚠️ Could not check migration status. Skipping migrations.'",
            "cd {$currentDir} && php artisan migrate --force || echo '⚠️ Migrate failed — continuing without aborting'",

            "cd {$currentDir} && sudo -u {$user} php artisan optimize:clear",

            "sudo chown -R {$user}:{$webGroup} {$newRelease}",
            "sudo chmod -R 750 {$newRelease}",
            "sudo chown -R {$user}:{$webGroup} {$newRelease}/storage",
            "sudo chmod -R 775 {$newRelease}/storage",

            "rm -rf {$newRelease}/public/storage",
            "ln -s {$newRelease}/storage/app/public {$newRelease}/public/storage",

            "sudo systemctl reload {$phpRestart} && sudo systemctl reload nginx",

            "ls -1t {$releasesDir} | tail -n +{$retainReleases} | xargs -I{} rm -rf {$releasesDir}/{}",
        ];

        if ($debug) {
            foreach ($final as $index => $command) {
                $output->writeln("<info>[FINAL:$index] Running:</info> $command");

                $result = $ssh->runWithStatus($command, timeout: 300);

                $output->writeln($result['output']);

                if (! $result['success']) {
                    error("❌ Final step failed at [FINAL:$index]: $command");

                    return Command::FAILURE;
                }
            }
        } else {
            $finalRun = $ssh->runWithStatus(implode(' && ', $final), timeout: 600);

            $output->writeln($finalRun['output']); // 👈 Show output from main deploy steps

            if (! $finalRun['success']) {
                error('❌ Final symlink or service reload failed. Site might still be running the previous release.');

                return Command::FAILURE;
            }
        }

        // if ($projectType === 'statamic') {
        //     $output->writeln("<info>⚡ Running Statamic stache:warm...</info>");
        //     $ssh->run("cd {$currentDir} && sudo -u {$user} php please stache:warm");
        // }

        outro("✅ Deployment complete! New release deployed at: {$newRelease}");

        return Command::SUCCESS;
    }

    protected function getPhpFpmService(string $os, string $phpVersion): string
    {
        return match (strtolower($os)) {
            'oracle' => 'php-fpm',
            default => "php{$phpVersion}-fpm",
        };
    }
}
