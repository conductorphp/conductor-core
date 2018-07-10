Conductor: Core
====================================

This module offers common core functionality for the Robofirm Conductor.


You should create a project with this command:

```bash
composer create-project zendframework/zend-expressive-skeleton myconductortool
```

Run `./myconductortool/vendor/bin/conductor` with no arguments to see all available commands. We recommend that you add `myconductortool/vendor/bin` to your path.

Below are a few of the most common Conductor modules we suggest:

1. [Application Orchestration](https://github.com/conductorphp/conductor-application-orchestration) - Application 
   installation, configuration, backups, builds, code deployments, maintenance mode, syncing of environments.

The Conductor supports many platforms. Here are a few common platforms:

* [Magento 2](https://github.com/conductorphp/conductor-magento-2-platform-support)
* [Magento 1](https://github.com/conductorphp/conductor-magento-1-platform-support)
* [Drupal](https://github.com/conductorphp/conductor-drupal-platform-support)
* [WordPress](https://github.com/conductorphp/conductor-wordpress-platform-support)

The Conductor can interact with a number of different filesystems. Here are a few common ones:

* [AWS](https://github.com/conductorphp/conductor-aws-s3-filesystem-support)
* [Azure](https://github.com/conductorphp/conductor-azure-blob-filesystem-support)

The Conductor currently only supports MySQL, but may support others in the future:

* [MySQL](https://github.com/conductorphp/conductor-mysql-database-support)

## How to Encrypt Configuration Values:

The Conductor supports encryption of all configuration values.

Update your `config/config.php` with the following:
```php
<?php
 
use ConductorCore\Crypt\Crypt;
use ConductorCore\YamlFileProvider;
use Zend\ConfigAggregator\ArrayProvider;
use Zend\ConfigAggregator\ConfigAggregator;
use Zend\ConfigAggregator\PhpFileProvider;
 
if (file_exists(__DIR__ . '/env.php')) {
    $environmentConfig = include __DIR__ . '/env.php';
}
 
$environment = $environmentConfig['environment'] ?? 'development';
$cryptKey = $environmentConfig['crypt_key'] ?? null;
 
// To enable or disable caching, set the `ConfigAggregator::ENABLE_CACHE` boolean in
// `config/autoload/local.php`.
$cacheConfig = [
    'config_cache_path' => 'data/config-cache.php',
];
 
$aggregator = new ConfigAggregator(
    [
        \ConductorCore\ConfigProvider::class,
        \Zend\Router\ConfigProvider::class,
        \Zend\Validator\ConfigProvider::class,
        // Include cache configuration
        new ArrayProvider($cacheConfig),
        // Default App module config
        App\ConfigProvider::class,
        // Load application config in a pre-defined order in such a way that local settings
        // overwrite global settings. (Loaded as first to last):
        //   - `global.php`
        //   - `*.global.php`
        //   - `environments/*/*.php`
        //   - `local.php`
        //   - `*.local.php`
        // @todo Add environment config here
        new PhpFileProvider('config/autoload/{,*.}global.php'),
        Crypt::decryptExpressiveConfig(new YamlFileProvider('config/app/{,*.}yaml'), $cryptKey),
        Crypt::decryptExpressiveConfig(new YamlFileProvider('config/app/environments/' . $environment . '/{,*.}yaml'), $cryptKey),
        new PhpFileProvider('config/autoload/{,*.}local.php'),
        // Load development config if it exists
        new PhpFileProvider('config/development.config.php'),
        new ArrayProvider(['environment' => $environment, 'crypt_key' => $cryptKey]),
    ], $cacheConfig['config_cache_path']
);
 
return $aggregator->getMergedConfig();
```

Create a file `config/env.php` with the following contents:
```php
<?php

return [
    'environment' => 'development',
    'crypt_key' => 'yourcryptkeyhere',
];
```

Generate an encryption key and save it by running:
```bash
./vendor/bin/conductor crypt:generate-key
```

Get the encrypted value for a string by writing it to a file, then running:
```bash
./vendor/bin/conductor crypt:encrypt --file yourplaintextfile.txt
```

Or, get the encrypted value for a string by running this directly:
```bash
./vendor/bin/conductor crypt:encrypt yourplaintextstring
```

Replace the plain text string in your configuration with the returned ciphertext including the wrapping ENC[] tag.
