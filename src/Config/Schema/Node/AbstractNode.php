<?php

declare(strict_types=1);

namespace ConductorCore\Config\Schema\Node;

use Closure;
use ConductorCore\Config\Schema\ParseResult;
use ConductorCore\Config\Schema\SchemaInterface;
use ConductorCore\Config\Schema\TransformerInterface;
use ConductorCore\Config\Schema\Undefined;

/**
 * Shared handling for every node: presence, defaults, explicit null, and the transform hook.
 *
 * Nodes are mutable builders (`$sb->string()->required()`), not value objects — a schema is
 * assembled once at construction and then only read.
 */
abstract class AbstractNode implements SchemaInterface
{
    private bool $required = false;
    private bool $nullable = false;
    private mixed $default = Undefined::VALUE;
    private TransformerInterface|Closure|null $transformer = null;

    /** @var list<Closure(mixed): ?string> */
    private array $assertions = [];

    public function required(): static
    {
        $this->required = true;

        return $this;
    }

    /** A default makes the key optional; it is returned as-is, without re-validation. */
    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function nullable(): static
    {
        $this->nullable = true;

        return $this;
    }

    /**
     * A rule the parsed value must satisfy, beyond its type.
     *
     * The callable returns null when the value is acceptable, or the message to report. It runs
     * AFTER type coercion and BEFORE the transformer, so an assertion sees a value of the declared
     * type and a transformer never sees one that failed.
     *
     * @param callable(mixed): ?string $assertion
     */
    public function assert(callable $assertion): static
    {
        $this->assertions[] = $assertion(...);

        return $this;
    }

    public function withTransformer(TransformerInterface|callable $transformer): static
    {
        $this->transformer = $transformer instanceof TransformerInterface
            ? $transformer
            : $transformer(...);

        return $this;
    }

    final public function parse(mixed $value, string $path = ''): ParseResult
    {
        if ($value === Undefined::VALUE) {
            if ($this->required) {
                return ParseResult::error($path, 'is required');
            }

            if ($this->default === Undefined::VALUE) {
                // Absent, not required, no default: stays absent so the Config can tell.
                return ParseResult::valid(Undefined::VALUE);
            }

            return ParseResult::valid($this->default);
        }

        if ($value === null) {
            return $this->nullable
                ? ParseResult::valid(null)
                : ParseResult::error($path, 'must not be null');
        }

        $result = $this->parseValue($value, $path);
        if (! $result->isValid()) {
            return $result;
        }

        foreach ($this->assertions as $assertion) {
            $message = $assertion($result->value);
            if ($message !== null) {
                return ParseResult::error($path, $message);
            }
        }

        return ParseResult::valid($this->transform($result->value));
    }

    private function transform(mixed $value): mixed
    {
        if ($this->transformer === null) {
            return $value;
        }

        return $this->transformer instanceof TransformerInterface
            ? $this->transformer->transform($value)
            : ($this->transformer)($value);
    }

    /** Validate and coerce a value that is present and non-null. */
    abstract protected function parseValue(mixed $value, string $path): ParseResult;
}
