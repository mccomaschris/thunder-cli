<?php

namespace ThundrLabs\ThundrCli\Support;

use Symfony\Component\Yaml\Yaml;

class ConfigManager
{
    public static function loadProjectConfig(?string $env = null): array
    {
        $path = getcwd().'/thundr.yml';

        if (! file_exists($path)) {
            throw new \RuntimeException('thundr.yml not found.');
        }

        $yaml = Yaml::parseFile($path);

        if ($env !== null) {
            if (! isset($yaml[$env])) {
                throw new \RuntimeException("Environment config '{$env}' not found in thundr.yml.");
            }

            return $yaml[$env];
        }

        // fallback to flat config if no environments
        return $yaml;
    }

    public static function loadGlobalConfig(): array
    {
        $path = $_SERVER['HOME'].'/.thundr/config.yml';

        if (! file_exists($path)) {
            throw new \RuntimeException("No global config found at: $path");
        }

        return Yaml::parseFile($path);
    }

    public static function globalConfigPath(): string
    {
        return ($_SERVER['HOME'] ?? getenv('HOME') ?: getenv('USERPROFILE')).'/.thundr/config.yml';
    }

    public static function projectConfigPath(): string
    {
        return getcwd().'/thundr.yml';
    }

    public static function saveGlobalConfig(array $config): void
    {
        $path = self::globalConfigPath();

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, Yaml::dump($config, 4, 2));
    }

    public static function loadRawProjectConfig(): array
    {
        $path = getcwd().'/thundr.yml';

        if (! file_exists($path)) {
            return [];
        }

        return Yaml::parseFile($path);
    }
}
