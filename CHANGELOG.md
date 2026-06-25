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