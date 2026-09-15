<?php

declare(strict_types=1);

namespace ConductorCore\Config;

use ConductorCore\Exception\UndefinedVariableException;

use function array_replace;
use function fnmatch;
use function getenv;
use function is_array;
use function is_int;
use function is_scalar;
use function is_string;
use function preg_match;
use function preg_replace_callback;
use function str_contains;

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
     * `${NAME}` to interpolate, `$${NAME}` for a literal.
     *
     * Group 1 is the escape marker (`$` or empty), group 2 the variable name. Public so any other
     * code that recognizes placeholders — {@see \ConductorAppOrchestration\Config\ReplacementConfig}
     * — agrees on exactly one syntax.
     */
    public const PLACEHOLDER_PATTERN = '/\$(\$)?\{([A-Za-z_][A-Za-z0-9_]*)\}/';

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
     * @param string $path Config path of the value, named in the error.
     * @throws UndefinedVariableException
     */
    public function interpolateString(string $value, string $path): string
    {
        $undefined = [];
        $value     = $this->substitute($value, $path, $undefined);

        if ($undefined !== []) {
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
     * @param list<array{string, string}> $undefined
     */
    private function substitute(string $value, string $path, array &$undefined): string
    {
        return (string) preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            function (array $matches) use ($path, &$undefined): string {
                [, $escaped, $name] = $matches;

                if ($escaped !== '') {
                    return '${' . $name . '}';
                }

                if (isset($this->variables[$name])) {
                    return $this->variables[$name];
                }

                $undefined[] = [$name, $path];

                return $matches[0];
            },
            $value,
        );
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
