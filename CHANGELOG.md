[5.0.0](https://github.com/conductorphp/conductor-core/compare/4.0.1...5.0.0) (2026-09-14)


<!--- CHANGELOG SPLIT MARKER -->

[4.0.1](https://github.com/conductorphp/conductor-core/compare/4.0.0...4.0.1) (2026-09-10)

### Bug Fixes
* a file into a directory destination on filesystem:sync (CTAP-1685) ([1249147](https://github.com/conductorphp/conductor-core/commit/12491477d44b563623ec2b1fd3e2523afcb78b28))

<!--- CHANGELOG SPLIT MARKER -->

[4.0.0](https://github.com/conductorphp/conductor-core/compare/3.1.1...4.0.0) (2026-09-08)

### Features
* a config schema layer for typed, validated package config (CTAP-1630) ([c7ea316](https://github.com/conductorphp/conductor-core/commit/c7ea3169c9207783c73fc5474e68f20668ea81a8))

<!--- CHANGELOG SPLIT MARKER -->

[3.1.1](https://github.com/conductorphp/conductor-core/compare/3.1.0...3.1.1) (2026-08-11)

### Bug Fixes
* the advertised PHP range to 8.4.1-8.5 (CTAP-1224) ([e0b9123](https://github.com/conductorphp/conductor-core/commit/e0b9123cc47e97be86bbde6b90f5a2f6e7bd4831))
* to phpunit 13 (CTAP-1226) ([1c060fb](https://github.com/conductorphp/conductor-core/commit/1c060fb6bd7407d03d94767a158fbc29361ab8d2))

<!--- CHANGELOG SPLIT MARKER -->

[3.1.0](https://github.com/conductorphp/conductor-core/compare/3.0.0...3.1.0) (2026-08-10)

### Features
* PHP 8.4.1+, symfony 8 and servicemanager 4 (CTAP-1224) ([6b3c29c](https://github.com/conductorphp/conductor-core/commit/6b3c29ca329f88ffbfa4749ef4e41511c93ec84a))
* amphp/amp with revolt/event-loop (CTAP-1223) ([efe2e32](https://github.com/conductorphp/conductor-core/commit/efe2e3236ce77c4709cdbdc5284f808fc682a197))
* symfony 7 (CTAP-1222) ([3fee4b7](https://github.com/conductorphp/conductor-core/commit/3fee4b70056bb81e2c2711f3136ba426c181d1f9))
* laminas-servicemanager v4 (CTAP-1221) ([bef05ef](https://github.com/conductorphp/conductor-core/commit/bef05ef6ef9787839ea0ba6e962e69d1fdfad303))

<!--- CHANGELOG SPLIT MARKER -->

[2.0.5](https://github.com/conductorphp/conductor-core/compare/2.0.4...2.0.5) (2026-07-24)

### Bug Fixes
* deprecated ReflectionProperty::setAccessible() call (CTAP-1021) ([0d02894](https://github.com/conductorphp/conductor-core/commit/0d02894d0311874f79571f0d6a1e7f8725e3d702))

<!--- CHANGELOG SPLIT MARKER -->

[2.0.4](https://github.com/conductorphp/conductor-core/compare/2.0.3...2.0.4) (2026-06-26)

### Bug Fixes
* abandoned flysystem-sftp with flysystem-sftp-v3 (CTAP-776) ([8d21b2b](https://github.com/conductorphp/conductor-core/commit/8d21b2ba6b3939f83b7618af2e78bea9497be67f))

<!--- CHANGELOG SPLIT MARKER -->

[2.0.3](https://github.com/conductorphp/conductor-core/compare/2.0.2...2.0.3) (2026-06-25)

### Bug Fixes
* release after publish fix ([396dd42](https://github.com/conductorphp/conductor-core/commit/396dd42ee4925f1d227f9b088a26ef927d09574c))

<!--- CHANGELOG SPLIT MARKER -->

[2.0.2](https://github.com/conductorphp/conductor-core/compare/2.0.1...2.0.2) (2026-06-25)

### Bug Fixes
* Composer 2 requirement ([3f46238](https://github.com/conductorphp/conductor-core/commit/3f46238eb7bcecc5a2fcb755ee0e4793441bd094))

<!--- CHANGELOG SPLIT MARKER -->

[2.0.1](https://github.com/conductorphp/conductor-core/compare/2.0.0...2.0.1) (2026-06-25)

### Bug Fixes
* PHP 8.2-8.5 support ([d14d2ee](https://github.com/conductorphp/conductor-core/commit/d14d2ee66b66a86218546efae12eed36e20d923a))

<!--- CHANGELOG SPLIT MARKER -->

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - Unreleased

### Added

- Added support for PHP 8.1 and 8.2

### Removed

- Removed support for PHP 8.0 and below

## [1.2.0] - Unreleased

### Added

- Added support for PHP 8.0 and 8.1

### Changed

- Added type hint to MountManager::getPrefixAndPath() $path variable

## [1.1.3] - 2022-06-20

### Fixed

- Improved error messaging.

## [1.1.2] - 2021-02-02

### Fixed

- Fixed concurrency related bug where child processes where not exiting.
- Updated ForkManager and LocalShellAdapter to ignore out of range exit statuses.

## [1.1.1] - 2021-01-28

### Fixed

- Replaced some echo statements with logger calls in ForkManager.
- Updated to throw exceptions instead of exit when errors occur in ForkManager.
- Replaced unnecessary `call_user_func_array` call with simple function call in ForkManager.
- Removed exit(0) of child processes as this is the default behavior anyways in ForkManager.

## [1.1.0] - 2021-01-28

### Added

- Added concurrency to ForkManager and filesystem SyncPlugin.

## [1.0.2] - 2021-01-28

### Fixed

- Reduced memory usage when syncing files.
- Added check if dir exists before creating when syncing files.

## [1.0.1] - 2021-01-21

### Fixed

- Updated file sync commands to not fork if batch size is 1.

## [1.0.0] - 2021-01-21

### Added

- Added `crypt:decrypt` command.
- Added `crypt:encrypt` command.
- Added `crypt:generate-key` command.
- Added `database:export` command.
- Added `database:import` command.
- Added `database:metadata` command.
- Added `database:table:metadata` command.
- Added `filesystem:copy` command.
- Added `filesystem:ls` command.
- Added `filesystem:mv` command.
- Added `filesystem:rm` command.
- Added `filesystem:sync` command.
- Added `shell:exec` command.