<?php

declare(strict_types=1);

namespace ConductorCore\Config;

use ConductorCore\Exception\RuntimeException;

use function array_key_exists;
use function count;
use function file_exists;
use function file_get_contents;
use function is_dir;
use function is_writable;
use function mkdir;
use function preg_replace;
use function realpath;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function substr;

/**
 * The directory conductor is configured from: the one holding `config/app/global.yaml`.
 *
 * Resolved once at launch from `--config <dir>`, then `CONDUCTOR_CONFIG_DIR`, then the working
 * directory, the way `composer` and `git` find theirs. Everything that used to locate `config/app`
 * relative to the composer install (`dirname(__DIR__, 5)` in application-orchestration) asks this
 * service instead, so the layout of the install no longer matters: a vendor dir, a phar, anything.
 * The launcher registers it in the container and, for modules that read config rather than
 * services, as `conductor.config_directory`.
 *
 * A directory laid out as a pre-standalone conductor host (`config/config.php` beside `config/app`,
 * a `composer.json` requiring conductor) is refused with the guide to convert it. There is no
 * compatibility mode; the decision on CTAP-1750 was one coordinated major (CTAP-1760).
 */
final readonly class ConfigDirectory
{
    public const OPTION       = 'config';
    public const ENV_VARIABLE = 'CONDUCTOR_CONFIG_DIR';
    public const MARKER       = 'config/app/global.yaml';

    public const MIGRATION_GUIDE = 'docs/migrations/2026/09/2026-09-18-conductor-standalone.md';

    /** @param string $path Absolute, without a trailing slash */
    public function __construct(public string $path)
    {
    }

    /**
     * @param string|null $option   The `--config` value, when given
     * @param string|null $variable The `CONDUCTOR_CONFIG_DIR` value, when set and non-empty
     * @param string      $workingDirectory Where conductor was started
     * @throws RuntimeException When the chosen directory is not a conductor configuration
     */
    public static function resolve(?string $option, ?string $variable, string $workingDirectory): self
    {
        [$path, $source] = match (true) {
            $option !== null && $option !== ''     => [$option, '--' . self::OPTION],
            $variable !== null && $variable !== '' => [$variable, self::ENV_VARIABLE],
            default                                => [$workingDirectory, 'the working directory'],
        };

        $real = is_dir($path) ? realpath($path) : false;
        if ($real === false) {
            throw new RuntimeException(sprintf('%s names "%s", which is not a directory.', $source, $path));
        }
        $real = rtrim($real, '/');

        if (self::isPreStandaloneHost($real)) {
            throw new RuntimeException(sprintf(
                '"%s" is laid out as a conductor host from before conductor/conductor: it has its own'
                . ' config/config.php or composer.json. Conductor now runs as one installed application against a'
                . ' configuration directory holding only config/app/**. Delete everything else, then run it again.'
                . ' Steps: %s.',
                $real,
                self::MIGRATION_GUIDE,
            ));
        }

        if (!file_exists($real . '/' . self::MARKER)) {
            throw new RuntimeException(sprintf(
                'No conductor configuration in "%s" (from %s): expected %s. Run conductor from the configuration'
                . ' directory, pass --%s <dir>, or set %s.',
                $real,
                $source,
                $real . '/' . self::MARKER,
                self::OPTION,
                self::ENV_VARIABLE,
            ));
        }

        return new self($real);
    }

    /**
     * The `--config` value in an argv, in either `--config=<dir>` or `--config <dir>` form, without
     * consulting Symfony Console: the container has to exist before the console does.
     *
     * @param list<string> $argv
     */
    public static function optionFromArgv(array $argv): ?string
    {
        $flag = '--' . self::OPTION;
        for ($i = 1, $n = count($argv); $i < $n; $i++) {
            if (str_starts_with($argv[$i], $flag . '=')) {
                return substr($argv[$i], strlen($flag) + 1);
            }
            if ($argv[$i] === $flag && array_key_exists($i + 1, $argv)) {
                return $argv[$i + 1];
            }
        }

        return null;
    }

    /** `<path>/config/app`: the YAML files and the `environments/<env>/` overrides. */
    public function appConfigPath(): string
    {
        return $this->path . '/config/app';
    }

    /** Where `skeleton.files` templates and post-import scripts are looked up before the environment's. */
    public function filesPath(): string
    {
        return $this->appConfigPath() . '/files';
    }

    public function environmentFilesPath(string $environment): string
    {
        return $this->appConfigPath() . '/environments/' . $environment . '/files';
    }

    /**
     * The merged-config cache for one conductor version and environment. The version is in the name
     * so swapping the installed conductor can never serve a cache written by the previous one.
     * Returns null when the cache directory cannot be created, and caching is then off.
     */
    public function cachePath(string $environment, string $version): ?string
    {
        $directory = $this->path . '/data/cache';
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return null;
        }
        if (!is_writable($directory)) {
            return null;
        }

        $safe = static fn(string $s): string => preg_replace('/[^A-Za-z0-9._-]+/', '_', $s);

        return sprintf('%s/config-cache-%s-%s.php', $directory, $safe($version), $safe($environment));
    }

    private static function isPreStandaloneHost(string $path): bool
    {
        if (file_exists($path . '/config/config.php')) {
            return true;
        }
        $composer = $path . '/composer.json';

        return file_exists($composer) && str_contains((string) file_get_contents($composer), '"conductor/core"');
    }
}
