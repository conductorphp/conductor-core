<?php

declare(strict_types=1);

namespace ConductorCore\Exception;

use function count;
use function implode;
use function sprintf;

/**
 * A `${NAME}` placeholder in configuration named a variable that is not defined.
 *
 * Thrown by {@see \ConductorCore\Config\EnvVarInterpolator} once per pass, carrying every
 * undefined reference it found, so a config with three missing variables is fixed in one round
 * rather than three. It is an {@see InvalidConfigException} because the remedy is the same as for
 * any other config problem: open the named path, then either define the variable where the
 * environment owner keeps such things or fix the reference.
 */
class UndefinedVariableException extends InvalidConfigException
{
    /**
     * @param list<array{string, string}> $references [variable name, config path] per miss
     */
    private function __construct(
        string $message,
        public readonly array $references,
    ) {
        parent::__construct($message);
    }

    /**
     * @param non-empty-list<array{string, string}> $references [variable name, config path] per miss
     */
    public static function forReferences(array $references): self
    {
        $count = count($references);

        if ($count === 1) {
            [[$name, $path]] = $references;
            $message = sprintf(
                'Undefined variable "%s" referenced in configuration at "%s".',
                $name,
                self::path($path),
            );
        } else {
            $lines = [];
            foreach ($references as [$name, $path]) {
                $lines[] = sprintf('  - "%s" at "%s"', $name, self::path($path));
            }

            $message = sprintf(
                "Configuration references %d undefined variables:\n%s",
                $count,
                implode("\n", $lines),
            );
        }

        $message .= "\nDefine it in the process environment (an empty value counts as unset), "
            . 'or write "$${NAME}" where a literal "${NAME}" is intended.';

        return new self($message, $references);
    }

    private static function path(string $path): string
    {
        return $path === '' ? '(root)' : $path;
    }
}
