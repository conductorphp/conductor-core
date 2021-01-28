# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - Unreleased
- Reduced memory usage when syncing files.

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
