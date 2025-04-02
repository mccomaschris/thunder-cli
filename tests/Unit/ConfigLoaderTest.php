<?php

use Mccomaschris\ThundrCli\Support\ConfigManager;

it('loads project config from a given path', function () {
    $yaml = <<<'YAML'
root_domain: example.com
php_version: '8.3'
repo: user/project
branch: main
project_type: laravel
server: example-server
operating_system: ubuntu
YAML;

    $tempFile = tempnam(sys_get_temp_dir(), 'thundr_');
    file_put_contents($tempFile, $yaml);

    $config = ConfigManager::loadProjectConfig($tempFile);

    expect($config['root_domain'])->toBe('example.com');
    expect($config['php_version'])->toEqual('8.3');
    expect($config['repo'])->toEqual('user/project');
    expect($config['branch'])->toEqual('main');
    expect($config['project_type'])->toEqual('laravel');
    expect($config['server'])->toEqual('example-server');
    expect($config['operating_system'])->toEqual('ubuntu');

    unlink($tempFile);
});

it('throws an error if the project config does not exist', function () {
    ConfigManager::loadProjectConfig('/invalid/path.yml');
})->throws(RuntimeException::class);

it('loads global config from ~/.thundr/config.yml', function () {
    $mockYaml = <<<'YAML'
cloudflare:
  api_token: abc123
servers:
  staging:
    ip: 192.168.1.10
    user: deploy
YAML;

    $home = sys_get_temp_dir().'/thundr-test-home';
    $globalPath = $home.'/.thundr/config.yml';

    mkdir($home.'/.thundr', 0777, true);
    file_put_contents($globalPath, $mockYaml);

    // Override $_SERVER['HOME'] to mock the home directory
    $_SERVER['HOME'] = $home;

    $config = ConfigManager::loadGlobalConfig();

    expect($config['cloudflare']['api_token'])->toBe('abc123');
    expect($config['servers']['staging']['ip'])->toBe('192.168.1.10');

    // Cleanup
    unlink($globalPath);
    rmdir($home.'/.thundr');
    rmdir($home);
});

it('throws an error if the global config does not exist', function () {
    $mockHome = sys_get_temp_dir().'/thundr-empty-home';
    $_SERVER['HOME'] = $mockHome;

    // Make sure the config doesn't exist
    if (file_exists($mockHome.'/.thundr/config.yml')) {
        unlink($mockHome.'/.thundr/config.yml');
    }

    ConfigManager::loadGlobalConfig();
})->throws(RuntimeException::class);
