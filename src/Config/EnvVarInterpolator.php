<?php

declare(strict_types=1);

namespace ConductorCore\Config;

use ConductorCore\Exception\InvalidArgumentException;
use ConductorCore\Exception\InvalidPlaceholderException;
use ConductorCore\Exception\UndefinedVariableException;

use function array_replace;
use function base64_decode;
use function fnmatch;
use function getenv;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_scalar;
use function is_string;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function sprintf;
use function str_contains;
use function substr;

/**
 * Fills `${NAME}` placeholders in a config tree from a set of variables, failing loudly on any it
 * cannot fill.
 *
 * This is the engine behind env-driven configuration (CTAP-1724): secrets and per-environment
 * values arrive as environment variables from whatever the environment owner already uses — a
 * `.env` file in development, the hosting platform's secret store in production — and the YAML
 * carries `${MYSQL_PASSWORD}` where the value goes. Conductor stops owning secrets at rest.
 *
 * ## Rules
 *
 * - `${NAME}` is replaced by the variable's value. `NAME` is `[A-Za-z_][A-Za-z0-9_]*`, the same
 *   set the shell accepts, so `${VAR:-default}` and friends are not placeholders and pass through.
 * - `${NAME|b64decode}` passes the value through a filter first. Filters are explicit at the point
 *   of use — a variable is never transformed because of how it is named — and there is exactly one
 *   today: `b64decode`, for the multi-line value (a PEM key) that travels as one base64 environment
 *   variable. An unknown filter name and a value the filter rejects are both errors at config load
 *   naming the variable, so a typo is not a silent no-op and a mangled key is not a deploy-time
 *   surprise ({@see InvalidPlaceholderException}).
 * - `$${NAME}` emits a literal `${NAME}`, for the rare template that needs one.
 * - An undefined variable is an error, never an empty string and never the literal passed through.
 *   Every undefined reference in the tree is reported in ONE exception, naming the variable and the
 *   config path (`application_orchestration.application.skeleton.files[config/autoload/db.php]…`),
 *   so a config is fixed in one pass rather than one deploy at a time.
 * - A variable whose value is the empty string counts as UNSET. Empty values are almost never
 *   intended: they are what `docker compose` passes through for a host variable nobody set, and
 *   what an `.env` line with nothing after the `=` produces. Treating them as unset means such a
 *   value falls through to the next source or fails, rather than rendering an empty password.
 * - Substitution is a single pass. A value that itself contains `${…}` is not expanded again, so
 *   a secret cannot inject a reference and there are no cycles to detect.
 * - Only strings are touched. Objects, closures, numbers and booleans in the tree are left as is.
 *
 * ## Skipped paths
 *
 * Some config is shell text that the shell will expand at run time with the same process
 * environment — plan step commands, where `${attempt}` is a shell loop variable that does not exist
 * at config-load time. `$skipPaths` takes {@see fnmatch()} patterns matched against the rendered
 * config path; a matching key's whole subtree is left untouched. The path syntax is the one the
 * schema errors use: `a.b.c` for identifier keys, `a[weird/key.php]` for anything else, `a[0]` for
 * list indexes.
 *
 * This class knows nothing about where variables come from beyond {@see fromProcessEnvironment()}.
 * The application-orchestration package layers `application.environment_vars` on top of it as a
 * fallback source and wires it into config loading as a ConfigAggregator post-processor.
 */
final class EnvVarInterpolator
{
    /**
     * `${NAME}` or `${NAME|filter}` to interpolate, `$${NAME}` for a literal.
     *
     * Group 1 is the escape marker (`$` or empty), group 2 the variable name, group 3 the filter
     * name or empty. Public so any other code that recognizes placeholders agrees on exactly one
     * syntax; {@see \ConductorAppOrchestration\Config\ReplacementConfig} goes further and uses this
     * class to do the filling, so there is one parser rather than one pattern in two places.
     */
    public const PLACEHOLDER_PATTERN = '/\$(\$)?\{([A-Za-z_][A-Za-z0-9_]*)(?:\|([A-Za-z_][A-Za-z0-9_]*))?\}/';

    /** The filters `${NAME|filter}` accepts. */
    public const FILTERS = ['b64decode'];

    /** @var array<string, string> Only defined (non-empty) variables. */
    private array $variables;

    /**
     * @param array<string, mixed> $variables name => value; non-scalar and empty values are dropped
     * @param list<string>         $skipPaths {@see fnmatch()} patterns of config paths to leave alone
     */
    public function __construct(array $variables, private readonly array $skipPaths = [])
    {
        $this->variables = self::defined($variables);
    }

    /**
     * The process environment, with `$fallback` supplying anything the environment does not.
     *
     * The environment wins when both define a variable. Because an empty value counts as unset, an
     * empty environment variable does NOT shadow a fallback value — `docker compose` passing through
     * an unset host variable as `MYSQL_HOST=` still lets a YAML constant apply.
     *
     * @param array<string, mixed> $fallback
     * @param list<string>         $skipPaths
     */
    public static function fromProcessEnvironment(array $fallback = [], array $skipPaths = []): self
    {
        $environment = getenv();

        return new self(array_replace(self::defined($fallback), self::defined($environment)), $skipPaths);
    }

    public function has(string $name): bool
    {
        return isset($this->variables[$name]);
    }

    /**
     * Fill every placeholder in `$config`.
     *
     * @param array<array-key, mixed> $config
     * @param string                  $path Config path of `$config` itself, prefixed to every reported path.
     * @return array<array-key, mixed>
     * @throws UndefinedVariableException Listing every undefined reference in the tree.
     */
    public function interpolate(array $config, string $path = ''): array
    {
        $undefined = [];
        $config    = $this->walk($config, $path, $undefined);

        if ($undefined !== []) {
            throw UndefinedVariableException::forReferences($undefined);
        }

        return $config;
    }

    /**
     * Fill every placeholder in one string.
     *
     * With `$failOnUndefined` false an undefined variable's placeholder is left as written instead of
     * being an error. That is the lenient mode {@see \ConductorAppOrchestration\Config\ReplacementConfig}
     * has always had for `replacements.*.to`, where a typo is meant to show up in the generated SQL;
     * it is not the mode for configuration, where a missing value must stop the deploy.
     *
     * @param string $path Config path of the value, named in the error.
     * @throws UndefinedVariableException  When `$failOnUndefined` and a variable is not defined.
     * @throws InvalidPlaceholderException On an unknown filter or a value the filter rejects, always.
     */
    public function interpolateString(string $value, string $path, bool $failOnUndefined = true): string
    {
        $undefined = [];
        $value     = $this->substitute($value, $path, $undefined);

        if ($failOnUndefined && $undefined !== []) {
            throw UndefinedVariableException::forReferences($undefined);
        }

        return $value;
    }

    /**
     * Usable directly as a `ConfigAggregator` post-processor.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function __invoke(array $config): array
    {
        return $this->interpolate($config);
    }

    /**
     * Render the config path of `$key` under `$path`, the way schema errors spell it.
     *
     * Identifier-like keys join with a dot. Anything else — a skeleton file name such as
     * `config/autoload/doctrine.local.php`, or a list index — is bracketed so the path stays
     * unambiguous for a reader who has to find the key in a YAML file.
     */
    public static function childPath(string $path, string|int $key): string
    {
        if (is_int($key)) {
            return "{$path}[$key]";
        }

        if (preg_match('/^[A-Za-z0-9_-]+$/', $key) === 1) {
            return $path === '' ? $key : "$path.$key";
        }

        return "{$path}[$key]";
    }

    /**
     * @param list<array{string, string}> $undefined Collects [variable, path] for every miss.
     */
    private function walk(mixed $value, string $path, array &$undefined): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $childPath = self::childPath($path, $key);
                if ($this->isSkipped($childPath)) {
                    continue;
                }

                $value[$key] = $this->walk($item, $childPath, $undefined);
            }

            return $value;
        }

        if (is_string($value) && str_contains($value, '${')) {
            return $this->substitute($value, $path, $undefined);
        }

        return $value;
    }

    /**
     * Pass a value through a named filter.
     *
     * Shared with the `b64decode` Twig filter for skeleton templates, so a value decodes the same
     * way whichever layer does it: whitespace stripped first (`base64` wraps at 76 columns and a
     * trailing newline is easy to carry along), then a STRICT decode — anything that is not base64
     * is an error, not a silently truncated key.
     *
     * @throws InvalidArgumentException On an unknown filter, or a value the filter rejects.
     */
    public static function applyFilter(string $filter, string $value): string
    {
        return match ($filter) {
            'b64decode' => self::base64Decode($value),
            default     => throw new InvalidArgumentException(sprintf(
                'Unknown filter "%s". Known filters: %s.',
                $filter,
                implode(', ', self::FILTERS),
            )),
        };
    }

    /**
     * @param list<array{string, string}> $undefined
     * @throws InvalidPlaceholderException
     */
    private function substitute(string $value, string $path, array &$undefined): string
    {
        return (string) preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            function (array $matches) use ($path, &$undefined): string {
                $escaped = $matches[1];
                $name    = $matches[2];
                $filter  = $matches[3] ?? '';

                if ($escaped !== '') {
                    // Everything after the leading `$`, filter included, verbatim.
                    return substr($matches[0], 1);
                }

                // A bad filter is a bad placeholder whether or not the variable is set: fail on the
                // typo now rather than after the operator has also defined the variable.
                if ($filter !== '' && ! in_array($filter, self::FILTERS, true)) {
                    throw InvalidPlaceholderException::unknownFilter($filter, $name, $path, self::FILTERS);
                }

                if (! isset($this->variables[$name])) {
                    $undefined[] = [$name, $path];

                    return $matches[0];
                }

                if ($filter === '') {
                    return $this->variables[$name];
                }

                try {
                    return self::applyFilter($filter, $this->variables[$name]);
                } catch (InvalidArgumentException $exception) {
                    throw InvalidPlaceholderException::filterFailed($filter, $name, $path, $exception->getMessage());
                }
            },
            $value,
        );
    }

    /** @throws InvalidArgumentException */
    private static function base64Decode(string $value): string
    {
        $decoded = base64_decode((string) preg_replace('/\s+/', '', $value), true);

        if ($decoded === false) {
            throw new InvalidArgumentException('value is not valid base64');
        }

        return $decoded;
    }

    private function isSkipped(string $path): bool
    {
        foreach ($this->skipPaths as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<array-key, mixed> $variables
     * @return array<string, string>
     */
    private static function defined(array $variables): array
    {
        $defined = [];
        foreach ($variables as $name => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $value = (string) $value;
            if ($value === '') {
                continue;
            }

            $defined[(string) $name] = $value;
        }

        return $defined;
    }
}
