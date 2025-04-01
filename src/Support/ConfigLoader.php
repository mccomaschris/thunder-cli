<?php

namespace Mccomaschris\ThundrCli\Support;

use Symfony\Component\Yaml\Yaml;

class ConfigLoader
{
    public static function loadProjectConfig(?string $path = null): array
    {
        $path ??= getcwd().'/thundr.yml';

        if (! file_exists($path)) {
            throw new \RuntimeException("No thundr.yml found at: $path");
        }

        return Yaml::parseFile($path);
    }

    public static function loadGlobalConfig(): array
    {
        $path = $_SERVER['HOME'].'/.thundr/config.yml';

        if (! file_exists($path)) {
            throw new \RuntimeException("No global config found at: $path");
        }

        return Yaml::parseFile($path);
    }
}
