Conductor Core Documentation
============================

This module offers common core functionality for [Conductor](https://github.com/conductorphp).

## Installation

```bash
composer require conductor/core
```

## Basic Usage

You should create a project with this command:

```bash
composer create-project laminas/laminas-expressive-skeleton myconductortool
```

Run `./myconductortool/vendor/bin/conductor` with no arguments to see all available commands. We recommend that you
add `myconductortool/vendor/bin` to your path.

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

## Configuration

Conductor merges its configuration from the installed packages' `ConfigProvider`s, then
`config/autoload/*.php`, then `config/app/*.yaml`, then `config/app/environments/<environment>/*.yaml`,
in that order, so an environment's own file overrides the shared `global.yaml`. The `config/config.php`
that does this is scaffolded below.

Two things about a running conductor come from the process environment rather than from a file: which
environment it is running against, and the values that differ per environment or must not be committed.

### Selecting the environment

`CONDUCTOR_ENVIRONMENT` selects the environment:

```bash
CONDUCTOR_ENVIRONMENT=production conductor app:deploy --plan production
```

An empty `CONDUCTOR_ENVIRONMENT` counts as unset. `CONDUCTOR_CRYPT_KEY` works the same way and is only
needed while the configuration still carries `ENC[...]` values (see
[Encrypting configuration values](#encrypting-configuration-values-enc)).

`CONDUCTOR_ENVIRONMENT` is required. When it is unset or empty, `EnvironmentConfig::resolve()` throws
naming the variable and `bin/conductor` exits 1, before any plan step runs. There is no default
environment and no file fallback (CTAP-1741): the process environment is the only source, so a
deploy runner that forgets the variable stops instead of deploying against `development`.

### Environment variables in configuration

Any string anywhere in the merged configuration may contain `${VAR}`. It is replaced, once, at config
load, with the value of the variable — so secrets and per-environment values arrive from whatever the
environment owner already uses, and the YAML carries only the reference:

```yaml
# config/app/environments/production/config.yaml
application_orchestration:
  application:
    skeleton:
      files:
        config/autoload/doctrine.local.php:
          location: local
          source: doctrine.local.php.twig
          template_vars:
            data:
              doctrine:
                connection:
                  orm_default:
                    params:
                      url: "mysql://prod:${MYSQL_PASSWORD}@${MYSQL_HOST}/noco_warranty"
```

```bash
CONDUCTOR_ENVIRONMENT=production MYSQL_HOST=db.internal MYSQL_PASSWORD=… conductor app:deploy
```

Conductor holds no secret at rest, needs no `crypt_key`, and the platform operator rotates a password
without touching the repository. This works for every section — skeleton `template_vars`,
`database.adapters.*`, `filesystem.adapters.*`, `environment_vars`, `replacements`, `servers` — because
the substitution runs over the whole merged config, not per consumer.

#### Where values come from

1. **The process environment.** In development that is a `.env` file loaded by whatever runs conductor
   (docker compose reads one by default). In production it is the hosting platform's secret store,
   surfaced as environment variables.
2. **`application_orchestration.application.environment_vars`**, for constants that are not secret and
   differ per environment. The current environment's `application.environments.<env>.environment_vars`
   overlays the shared map. These values may themselves reference process environment variables.

The process environment wins when both define a variable.

A variable whose value is the **empty string counts as unset**. Empty values are almost never meant:
they are what `docker compose` passes through for a host variable nobody set, and what an `.env` line
with nothing after the `=` produces. An empty variable therefore falls through to `environment_vars`,
or fails, rather than rendering an empty password.

#### The `.env.dist` convention

Commit a `.env.dist` next to the conductor project holding every variable the configuration references,
with development defaults where a default is safe and blank where a real value is required:

```dotenv
# .env.dist — copy to .env (gitignored) and fill in the blanks
CONDUCTOR_ENVIRONMENT=development
MYSQL_HOST=mysql
MYSQL_PASSWORD=
```

A new checkout copies it to `.env`, fills in the blanks, and the first `conductor` run tells them exactly
which blank they missed.

#### Undefined variables fail the load

A placeholder naming a variable that is not defined is an error — never an empty string, never the
literal `${VAR}` written into a config file. Every undefined reference in the configuration is reported
in one message, naming the variable and the config path, before any plan step runs:

```
Configuration references 2 undefined variables:
  - "MYSQL_PASSWORD" at "application_orchestration.application.skeleton.files[config/autoload/doctrine.local.php].template_vars.data.doctrine.connection.orm_default.params.url"
  - "MYSQL_HOST" at "database.adapters.default.arguments.host"
Define it in the process environment (an empty value counts as unset), or write "$${NAME}" where a literal "${NAME}" is intended.
```

`conductor` exits non-zero, so a CI step or a platform's deploy hook fails rather than deploying with
a phantom value.

#### Writing a literal `${VAR}`

`$${VAR}` renders as the literal text `${VAR}`, for the rare template that needs one. Only `${NAME}`
and `${NAME|filter}` with a shell-style name (`[A-Za-z_][A-Za-z0-9_]*`) are placeholders;
`${VAR:-default}` and `$VAR` are not and pass through untouched.

Substitution is a single pass: a value that itself contains `${…}` is not expanded again, so a secret
cannot inject a reference.

#### Plan steps are left to the shell

Everything under a plan's `steps` (and `preflight_steps`, `clean_steps`, `rollback_*_steps`) is shell
text. `PlanRunner` runs it under `bash` with the process environment already merged in, so `${VAR}`
there is expanded by the shell at run time, and a shell variable such as `${attempt}` in a retry loop
is not mistaken for configuration. Nothing changes for existing plans.

#### Multi-line values (PEM keys, certificates)

Environment variables are single-line by convention; a PEM key is not. Put the value in the variable
**base64 encoded**:

```bash
AMAZON_PAY_PRIVATE_KEY=$(base64 -w0 < amazon-pay-private.pem)
```

Then decode it at the point of use with a **filter on the placeholder**:

```yaml
template_vars:
  data:
    AMAZON_PAY:
      private_key: '${AMAZON_PAY_PRIVATE_KEY|b64decode}'
```

The variable's value is decoded before it is written into the config, so the file rendered from it —
here through conductor's own `var-export.php.twig`, which has no per-field hook — receives the PEM
itself. This is the form to use whenever the value goes through a shared template, or is read by
anything other than a template you own.

The filter is explicit on purpose. Nothing is decoded because of how a variable is *named*; the
variable is named for what it is, and `|b64decode` says what to do with it. `b64decode` is the only
filter today.

The alternative, for a Twig template the project owns, is the same filter inside the template:

```yaml
template_vars:
  jwt_private_key: '${JWT_PRIVATE_KEY}'
```

```twig
{# config/autoload/jwt.local.php.twig #}
<?php
return [
    'private_key' => {{ jwt_private_key|b64decode|var_export }},
];
```

Both routes decode identically: whitespace is stripped first (`base64` wraps at 76 columns unless told
`-w0`), then a strict decode, so wrapped input and a trailing newline are fine and anything that is not
base64 is an error naming the variable — at config load for the placeholder form, at deploy time for
the Twig form — rather than a truncated key. A misspelled filter name is an error too, not a silent
no-op. A `${file:/path}` source that reads a mounted secret file directly is a possible follow-up; it is
not supported today.

#### Config caching

When `ConfigAggregator::ENABLE_CACHE` is on, the merged configuration — with placeholders filled and
`ENC[...]` values decrypted — is written to `config_cache_path` and read back on later runs without
consulting the environment again. Clear that file when a variable changes, and treat it as sensitive:
it holds every secret in plaintext. Development mode (`composer development-enable`) disables the cache.

### The `config/config.php` scaffold

```php
<?php

use ConductorAppOrchestration\Config\EnvVarInterpolationPostProcessor;
use ConductorCore\Config\EnvironmentConfig;
use ConductorCore\Crypt\Crypt;
use ConductorCore\YamlFileProvider;
use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;

// CONDUCTOR_ENVIRONMENT / CONDUCTOR_CRYPT_KEY from the process environment.
$environmentConfig = EnvironmentConfig::resolve();
$environment = $environmentConfig->environment;
$cryptKey = $environmentConfig->cryptKey;

// To enable or disable caching, set the `ConfigAggregator::ENABLE_CACHE` boolean in
// `config/autoload/local.php`.
$cacheConfig = [
    'config_cache_path' => 'data/config-cache.php',
];

$aggregator = new ConfigAggregator(
    [
        \ConductorCore\ConfigProvider::class,
        \Laminas\Router\ConfigProvider::class,
        \Laminas\Validator\ConfigProvider::class,
        // Include cache configuration
        new ArrayProvider($cacheConfig),
        // Default App module config
        App\ConfigProvider::class,
        // Load application config in a pre-defined order in such a way that local settings
        // overwrite global settings. (Loaded as first to last):
        //   - `global.php`
        //   - `*.global.php`
        //   - `config/app/*.yaml`
        //   - `config/app/environments/<environment>/*.yaml`
        //   - `local.php`
        //   - `*.local.php`
        new PhpFileProvider('config/autoload/{,*.}global.php'),
        Crypt::decryptExpressiveConfig(new YamlFileProvider('config/app/{,*.}yaml'), $cryptKey),
        Crypt::decryptExpressiveConfig(new YamlFileProvider('config/app/environments/' . $environment . '/{,*.}yaml'), $cryptKey),
        new PhpFileProvider('config/autoload/{,*.}local.php'),
        // Load development config if it exists
        new PhpFileProvider('config/development.config.php'),
        new ArrayProvider($environmentConfig->toArray()),
    ],
    $cacheConfig['config_cache_path'],
    [
        // `${VAR}` placeholders anywhere in the merged config. Undefined variables fail the load.
        new EnvVarInterpolationPostProcessor(),
    ]
);

return $aggregator->getMergedConfig();
```

`EnvVarInterpolationPostProcessor` comes from `conductor/application-orchestration`, which knows where
`environment_vars` and plan steps live. A project using `conductor/core` alone can register
`\ConductorCore\Config\EnvVarInterpolator::fromProcessEnvironment()` as the post-processor instead and
get the process environment as the only source.

### Encrypting configuration values (`ENC[...]`)

Before `${VAR}` interpolation, the only way to carry a secret into configuration was to encrypt it in
place. It still works, and the two coexist in one configuration: a value is either an `ENC[...]` string
or contains `${VAR}` placeholders, and each mechanism ignores the other's syntax. Prefer `${VAR}` for
new configuration — it needs no key, and the platform that owns the environment owns the secret. A
configuration with no `ENC[...]` values needs no `crypt_key` at all.

The one interaction to know: decryption runs before interpolation, so a decrypted plaintext that
happens to contain a `${NAME}` sequence would be interpolated. Carry such a value as a base64
environment variable instead.

Set the key with `CONDUCTOR_CRYPT_KEY`:

```bash
CONDUCTOR_ENVIRONMENT=production CONDUCTOR_CRYPT_KEY=yourcryptkeyhere conductor app:deploy --plan production
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

Note that a value encrypted in `global.yaml` must decrypt under every environment's key, which in
practice forces one key across all environments. Values that differ per environment belong in that
environment's file, or in a `${VAR}`.

## Known Issues

### Forking SSL Issue

If you encounter this error or similar while running `conductor app:deploy --snapshot mysnapshot --assets`
or `conductor app:snapshot mysnapshot --assets`, read below:

```
cURL error 35: A PKCS #11 module returned CKR_DEVICE_ERROR, indicating that a problem has occurred with the 
token or slot. (see http://curl.haxx.se/libcurl/c/libcurl-errors.html)
```

NSS has a bug in older versions which causes this issue when forking a PHP process. A patch was added to
force NSS to reinitialize on PHP process fork. If you see this error, you are running an older version of
NSS or curl compiled with older NSS.

You can work around this issue by adding this line to your `config/config.php` after the namespace definitions.

```php
putenv("NSS_STRICT_NOFORK=DISABLED");
```

Alternatively, you can also run this in all environments where file syncing via Conductor is done.

```bash
export NSS_STRICT_NOFORK=DISABLED
```
