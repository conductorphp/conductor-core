DevOps Tool: Core
====================================

This module offers common core functionality for the  
[Robofirm DevOps Tool](https://bitbucket.org/robofirm/robofirm-devops).


You should create a project with this command:

```bash
composer create-project zendframework/zend-expressive-skeleton mydevopstool
```

Run `./mydevopstool/vendor/bin/devops` with no arguments to see all available commands. We recommend add `bin` to your path

Below are a few of the most common DevOps Tool modules we suggest:

1. [Application Orchestration](https://bitbucket.org/robofirm/devops-application-orchestration) - Application 
   installation, configuration, backups, builds, code deployments, maintenance mode, syncing of environments.
2. [Project Setup](https://bitbucket.org/robofirm/devops-project-setup) - Creation of initial project resources
   including Jira project, Jira user groups, pre-populated Confluence space, pre-populated 
   Bitbucket repositories for Terraform, Puppet, and App Setup.

The DevOps Tool supports many platforms. Here are a few common platforms:

* [Magento 2](https://bitbucket.org/robofirm/devops-magento-2-platform-support)
* [Magento 1](https://bitbucket.org/robofirm/devops-magento-1-platform-support)
* [Drupal](https://bitbucket.org/robofirm/devops-drupal-platform-support)
* [WordPress](https://bitbucket.org/robofirm/devops-wordpress-platform-support)

The DevOps Tool can interact with a number of different filesystems. Here are a few common ones:

* [AWS](https://bitbucket.org/robofirm/devops-aws-filesystem-support)
* [Azure](https://bitbucket.org/robofirm/devops-azure-filesystem-support)

The DevOps Tool currently only supports MySQL, but may support others in the future:

* [MySQL](https://bitbucket.org/robofirm/devops-mysql-database-support)

## How to Encrypt Configuration Values:

The DevOps Tool supports encryption of all configuration values.

Update your `config/config.php` with the following:
```php
<?php

use DevopsToolCore\Crypt\Crypt;
use Zend\ConfigAggregator\ArrayProvider;
use Zend\ConfigAggregator\ConfigAggregator;
use Zend\ConfigAggregator\PhpFileProvider;

$environment = $_SERVER['APPLICATION_ENV'] ?? 'development';
$cryptKey = null;
if (file_exists(__DIR__ . '/crypt_key.txt')) {
    $cryptKey = file_get_contents(__DIR__ . '/crypt_key.txt');
}

// To enable or disable caching, set the `ConfigAggregator::ENABLE_CACHE` boolean in
// `config/autoload/local.php`.
$cacheConfig = [
    'config_cache_path' => 'data/config-cache.php',
];

$aggregator = new ConfigAggregator(
    [
        // Other modules here
        \DevopsToolCore\ConfigProvider::class,

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
        Crypt::decryptExpressiveConfig(new PhpFileProvider('config/autoload/{,*.}global.php'), $cryptKey),
        Crypt::decryptExpressiveConfig(new PhpFileProvider('config/autoload/environments/' . $environment . '/{,*.}php'), $cryptKey),
        new PhpFileProvider('config/autoload/{,*.}local.php'),
        // Load development config if it exists
        new PhpFileProvider('config/development.config.php'),
        new ArrayProvider(['environment' => $environment, 'crypt_key' => $cryptKey]),
    ], $cacheConfig['config_cache_path']
);

return $aggregator->getMergedConfig();
```

Generate an encryption key and save it by running:
```bash
./vendor/bin/devops crypt:generate-key > config/crypt_key.txt
```

Get the encrypted value for a string by running, then replace the plain text string in your config:
```bash
./vendor/bin/devops crypt:encrypt yourplaintextstring
```
