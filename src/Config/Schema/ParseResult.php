<?php

declare(strict_types=1);

namespace ConductorCore\Config\Schema;

use function array_merge;

/**
 * The outcome of parsing one config sub-tree against a schema.
 *
 * Errors are collected rather than thrown one at a time, so a caller learns everything wrong with a
 * config in a single run instead of fixing one key, redeploying, and finding the next.
 */
final readonly class ParseResult
{
    /** @param list<string> $errors Human-readable, each already prefixed with its config path. */
    private function __construct(
        public mixed $value,
        public array $errors,
    ) {
    }

    public static function valid(mixed $value): self
    {
        return new self($value, []);
    }

    public static function error(string $path, string $message): self
    {
        return new self(null, [($path === '' ? '' : "$path: ") . $message]);
    }

    /** @param list<string> $errors */
    public static function errors(array $errors): self
    {
        return new self(null, $errors);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function withErrorsFrom(self ...$others): self
    {
        $errors = $this->errors;
        foreach ($others as $other) {
            $errors = array_merge($errors, $other->errors);
        }

        return new self($this->value, $errors);
    }
}
