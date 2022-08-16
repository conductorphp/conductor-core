# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - Unreleased
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
