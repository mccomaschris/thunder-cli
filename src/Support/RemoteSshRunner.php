<?php

namespace ThundrLabs\ThundrCli\Support;

use Symfony\Component\Process\Process;

class RemoteSshRunner
{
    protected string $user;

    protected string $host;

    protected ?string $sshKey;

    public function __construct(string $user, string $host, ?string $sshKey = null)
    {
        $this->user = $user;
        $this->host = $host;
        $this->sshKey = $sshKey;
    }

    public static function make(array $server): self
    {
        return new self(
            user: $server['user'],
            host: $server['host'],
            sshKey: $server['ssh_key'] ?? null,
        );
    }

    public function run(string $command): string|false
    {
        $fullCommand = 'ssh ';

        if ($this->sshKey) {
            $fullCommand .= "-i {$this->sshKey} ";
        }

        $fullCommand .= "{$this->user}@{$this->host} '{$command}'";

        $output = shell_exec($fullCommand);

        return $output !== null ? trim($output) : false;
    }

    public function runWithStatus(string $command, int $timeout = 300): array
    {
        $process = Process::fromShellCommandline($this->buildSshCommand($command));
        $process->setTimeout($timeout); // <-- Add this line
        $process->run();

        return [
            'success' => $process->isSuccessful(),
            'output' => trim($process->getOutput()),
        ];
    }

    public function download(string $remotePath, string $localPath): void
    {
        $cmd = 'scp '.$this->buildSshOptions()."{$this->user}@{$this->host}:{$remotePath} {$localPath}";
        shell_exec($cmd);
    }

    public function upload(string $localPath, string $remotePath): void
    {
        $cmd = 'scp '.$this->buildSshOptions()."{$localPath} {$this->user}@{$this->host}:{$remotePath}";
        shell_exec($cmd);
    }

    protected function buildSshOptions(): string
    {
        return $this->sshKey ? "-i {$this->sshKey} " : '';
    }

    private function buildSshCommand(string $command): string
    {
        $sshOptions = $this->sshKey ? "-i {$this->sshKey}" : '';

        return "ssh {$sshOptions} {$this->user}@{$this->host} '{$command}'";
    }

    public function buildRawLoginCommand(): string
    {
        $cmd = 'ssh ';

        if ($this->sshKey) {
            $cmd .= "-i {$this->sshKey} ";
        }

        $cmd .= "{$this->user}@{$this->host}";

        return $cmd;
    }
}
