<?php

declare(strict_types=1);

namespace ConductorCore\Runtime;

use ConductorCore\Shell\Adapter\ShellAdapterInterface;

use function extension_loaded;
use function sprintf;

/**
 * Something an adapter needs from the host it runs on: a PHP extension or a command on `PATH`.
 *
 * Declared by an adapter through {@see RuntimeRequirementsInterface}, checked by
 * {@see RequirementChecker} when the adapter is selected by configuration and again at use, never
 * at install. A module `suggest`s its extension or command in composer.json instead of requiring
 * it, so installing every module does not force every runtime onto every host (CTAP-1759).
 */
final readonly class Requirement
{
    public const KIND_EXTENSION = 'extension';
    public const KIND_BINARY    = 'binary';

    private function __construct(
        public string $kind,
        public string $name,
        public ?string $hint,
    ) {
    }

    /** @param string|null $hint How to satisfy it, e.g. `docker-php-ext-install pdo_mysql` */
    public static function extension(string $name, ?string $hint = null): self
    {
        return new self(self::KIND_EXTENSION, $name, $hint);
    }

    /** @param string|null $hint How to satisfy it, e.g. the package that ships the command */
    public static function binary(string $name, ?string $hint = null): self
    {
        return new self(self::KIND_BINARY, $name, $hint);
    }

    /**
     * A command is looked up through the shell adapter that will run it, so the answer is true for
     * where the command runs, not necessarily where conductor runs.
     */
    public function isSatisfied(ShellAdapterInterface $shell): bool
    {
        return match ($this->kind) {
            self::KIND_EXTENSION => extension_loaded($this->name),
            self::KIND_BINARY    => $shell->isCallable($this->name),
        };
    }

    public function describe(): string
    {
        $what = match ($this->kind) {
            self::KIND_EXTENSION => sprintf('PHP extension "%s"', $this->name),
            self::KIND_BINARY    => sprintf('command "%s"', $this->name),
        };

        return $this->hint === null ? $what : sprintf('%s (%s)', $what, $this->hint);
    }
}
