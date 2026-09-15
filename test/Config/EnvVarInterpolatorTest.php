<?php

declare(strict_types=1);

namespace ConductorCoreTest\Config;

use ConductorCore\Config\EnvVarInterpolator;
use ConductorCore\Exception\InvalidArgumentException;
use ConductorCore\Exception\InvalidConfigException;
use ConductorCore\Exception\InvalidPlaceholderException;
use ConductorCore\Exception\UndefinedVariableException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

use function base64_encode;
use function chunk_split;
use function putenv;

/**
 * CTAP-1724. `${VAR}` interpolation across a config tree, failing loudly on an undefined variable.
 */
class EnvVarInterpolatorTest extends TestCase
{
    private const ENV = 'CONDUCTOR_TEST_INTERPOLATOR_VAR';

    protected function tearDown(): void
    {
        putenv(self::ENV);
        putenv(self::ENV . '_EMPTY');
    }

    // ------------------------------------------------------------------ substitution

    public function testFillsPlaceholdersAnywhereInTheTree(): void
    {
        $interpolator = new EnvVarInterpolator(['MYSQL_HOST' => 'db.internal', 'MYSQL_PASSWORD' => 's3cret']);

        $config = $interpolator->interpolate([
            'database' => [
                'adapters' => [
                    'default' => [
                        'arguments' => [
                            'host'     => '${MYSQL_HOST}',
                            'password' => '${MYSQL_PASSWORD}',
                            'port'     => 3306,
                        ],
                    ],
                ],
            ],
            'url' => 'mysql://prod:${MYSQL_PASSWORD}@${MYSQL_HOST}/noco_warranty',
        ]);

        $this->assertSame('db.internal', $config['database']['adapters']['default']['arguments']['host']);
        $this->assertSame('s3cret', $config['database']['adapters']['default']['arguments']['password']);
        $this->assertSame(3306, $config['database']['adapters']['default']['arguments']['port']);
        $this->assertSame('mysql://prod:s3cret@db.internal/noco_warranty', $config['url']);
    }

    public function testEscapedPlaceholderRendersLiterally(): void
    {
        $interpolator = new EnvVarInterpolator(['VAR' => 'value']);

        $this->assertSame(
            'literal ${VAR} next to value',
            $interpolator->interpolateString('literal $${VAR} next to ${VAR}', 'x'),
        );
    }

    /** Only `${NAME}` is a placeholder; shell forms and stray dollars pass through untouched. */
    #[DataProvider('notPlaceholders')]
    public function testShellSyntaxThatIsNotAPlaceholderPassesThrough(string $value): void
    {
        $interpolator = new EnvVarInterpolator([]);

        $this->assertSame($value, $interpolator->interpolateString($value, 'x'));
    }

    /** @return iterable<string, array{string}> */
    public static function notPlaceholders(): iterable
    {
        yield 'default form'  => ['${VAR:-default}'];
        yield 'required form' => ['${VAR:?message}'];
        yield 'no braces'     => ['$VAR'];
        yield 'bad name'      => ['${9VAR}'];
        yield 'empty'         => ['${}'];
        yield 'price'         => ['costs $5'];
    }

    /** A value is substituted once; what it contains is data, not more placeholders. */
    public function testSubstitutionIsASinglePass(): void
    {
        $interpolator = new EnvVarInterpolator(['OUTER' => '${INNER}', 'INNER' => 'never']);

        $this->assertSame('${INNER}', $interpolator->interpolateString('${OUTER}', 'x'));
    }

    public function testNonStringsAreLeftAlone(): void
    {
        $object       = new stdClass();
        $closure      = static fn(): string => '${VAR}';
        $interpolator = new EnvVarInterpolator(['VAR' => 'value']);

        $config = $interpolator->interpolate([
            'object'  => $object,
            'closure' => $closure,
            'int'     => 42,
            'bool'    => true,
            'null'    => null,
            'list'    => ['${VAR}', 7],
        ]);

        $this->assertSame($object, $config['object']);
        $this->assertSame($closure, $config['closure']);
        $this->assertSame(42, $config['int']);
        $this->assertTrue($config['bool']);
        $this->assertNull($config['null']);
        $this->assertSame(['value', 7], $config['list']);
    }

    // ------------------------------------------------------------------ filters (CTAP-1728)

    /** The shared `var-export.php.twig` has no per-field hook, so the decode has to happen here. */
    public function testB64decodeFilterDecodesTheValue(): void
    {
        $pem          = "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkq\n-----END PRIVATE KEY-----\n";
        $interpolator = new EnvVarInterpolator(['AMAZON_PAY_PRIVATE_KEY' => base64_encode($pem)]);

        $config = $interpolator->interpolate([
            'data' => ['AMAZON_PAY' => ['private_key' => '${AMAZON_PAY_PRIVATE_KEY|b64decode}']],
        ]);

        $this->assertSame($pem, $config['data']['AMAZON_PAY']['private_key']);
    }

    /** `base64` wraps at 76 columns and appends a newline unless told otherwise; both are tolerated. */
    public function testB64decodeToleratesWrappedInputWithATrailingNewline(): void
    {
        $pem          = "-----BEGIN CERTIFICATE-----\nMIIB\n-----END CERTIFICATE-----\n";
        $interpolator = new EnvVarInterpolator(['TLS_CERT' => chunk_split(base64_encode($pem), 76, "\n")]);

        $this->assertSame($pem, $interpolator->interpolateString('${TLS_CERT|b64decode}', 'x'));
    }

    /** Proven by making it fail: not base64 is an error naming the variable, never a truncated key. */
    public function testAValueThatIsNotBase64IsAnErrorNamingTheVariableAndPath(): void
    {
        $interpolator = new EnvVarInterpolator(['AMAZON_PAY_PRIVATE_KEY' => '-----BEGIN PRIVATE KEY-----']);

        try {
            $interpolator->interpolate([
                'template_vars' => ['data' => ['private_key' => '${AMAZON_PAY_PRIVATE_KEY|b64decode}']],
            ]);
            $this->fail('Expected InvalidPlaceholderException');
        } catch (InvalidPlaceholderException $exception) {
            $this->assertStringContainsString('"b64decode"', $exception->getMessage());
            $this->assertStringContainsString('"AMAZON_PAY_PRIVATE_KEY"', $exception->getMessage());
            $this->assertStringContainsString('template_vars.data.private_key', $exception->getMessage());
            $this->assertStringContainsString('not valid base64', $exception->getMessage());
            $this->assertInstanceOf(InvalidConfigException::class, $exception);
        }
    }

    /** A typo in the filter name is not a silent no-op, and is reported even if the variable is unset. */
    public function testAnUnknownFilterFailsLoudly(): void
    {
        $interpolator = new EnvVarInterpolator([]);

        try {
            $interpolator->interpolateString('${KEY|base64decode}', 'template_vars.key');
            $this->fail('Expected InvalidPlaceholderException');
        } catch (InvalidPlaceholderException $exception) {
            $this->assertStringContainsString('Unknown filter "base64decode"', $exception->getMessage());
            $this->assertStringContainsString('"${KEY|base64decode}"', $exception->getMessage());
            $this->assertStringContainsString('template_vars.key', $exception->getMessage());
            $this->assertStringContainsString('b64decode', $exception->getMessage());
        }
    }

    public function testAPlaceholderWithoutAFilterIsUnchangedInBehavior(): void
    {
        $encoded      = base64_encode('raw');
        $interpolator = new EnvVarInterpolator(['KEY' => $encoded]);

        $this->assertSame($encoded, $interpolator->interpolateString('${KEY}', 'x'));
    }

    public function testAnUndefinedVariableWithAFilterIsStillReportedAsUndefined(): void
    {
        $interpolator = new EnvVarInterpolator([]);

        try {
            $interpolator->interpolate(['a' => '${MISSING_KEY|b64decode}', 'b' => '${ALSO_MISSING}']);
            $this->fail('Expected UndefinedVariableException');
        } catch (UndefinedVariableException $exception) {
            $this->assertSame([['MISSING_KEY', 'a'], ['ALSO_MISSING', 'b']], $exception->references);
        }
    }

    public function testAnEmptyValueWithAFilterCountsAsUnset(): void
    {
        $interpolator = new EnvVarInterpolator(['EMPTY_KEY' => '']);

        $this->expectException(UndefinedVariableException::class);

        $interpolator->interpolateString('${EMPTY_KEY|b64decode}', 'x');
    }

    public function testEscapedPlaceholderWithAFilterRendersLiterally(): void
    {
        $interpolator = new EnvVarInterpolator(['KEY' => base64_encode('x')]);

        $this->assertSame('${KEY|b64decode}', $interpolator->interpolateString('$${KEY|b64decode}', 'x'));
    }

    /** A decoded value containing `${…}` is data, not a placeholder to expand. */
    public function testADecodedValueIsNotExpandedAgain(): void
    {
        $interpolator = new EnvVarInterpolator(['B64' => base64_encode('${INNER}'), 'INNER' => 'never']);

        $this->assertSame('${INNER}', $interpolator->interpolateString('${B64|b64decode}', 'x'));
    }

    public function testFilteredPlaceholdersInASkippedSubtreeAreLeftAlone(): void
    {
        $interpolator = new EnvVarInterpolator(['KEY' => 'not*base64'], ['*.plans.*.steps']);
        $steps        = ['write-key' => 'echo "${KEY|b64decode}" > key.pem'];

        $config = $interpolator->interpolate(['deploy' => ['plans' => ['default' => ['steps' => $steps]]]]);

        $this->assertSame($steps, $config['deploy']['plans']['default']['steps']);
    }

    /** The Twig `b64decode` filter decodes through this, so both layers agree. */
    public function testApplyFilterIsTheSharedDecoder(): void
    {
        $this->assertSame('raw', EnvVarInterpolator::applyFilter('b64decode', base64_encode('raw')));
        $this->assertSame(['b64decode'], EnvVarInterpolator::FILTERS);

        $this->expectException(InvalidArgumentException::class);

        EnvVarInterpolator::applyFilter('rot13', 'x');
    }

    // ------------------------------------------------------------------ failing loudly

    public function testUndefinedVariableNamesTheVariableAndTheConfigPath(): void
    {
        $interpolator = new EnvVarInterpolator([]);

        try {
            $interpolator->interpolate([
                'application_orchestration' => [
                    'application' => [
                        'skeleton' => [
                            'files' => [
                                'config/autoload/doctrine.local.php' => [
                                    'template_vars' => [
                                        'data' => ['doctrine' => ['connection' => ['orm_default' => ['params' => [
                                            'url' => 'mysql://prod:${MYSQL_PASSWORD}@host/db',
                                        ]]]]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
            $this->fail('Expected UndefinedVariableException');
        } catch (UndefinedVariableException $exception) {
            $this->assertStringContainsString('Undefined variable "MYSQL_PASSWORD"', $exception->getMessage());
            $this->assertStringContainsString(
                'application_orchestration.application.skeleton.files[config/autoload/doctrine.local.php]'
                . '.template_vars.data.doctrine.connection.orm_default.params.url',
                $exception->getMessage(),
            );
            $this->assertStringContainsString('$${NAME}', $exception->getMessage());
            $this->assertInstanceOf(InvalidConfigException::class, $exception);
        }
    }

    /** Every miss in one exception, so a config is fixed in one round rather than one per deploy. */
    public function testEveryUndefinedVariableIsReportedTogether(): void
    {
        $interpolator = new EnvVarInterpolator(['KNOWN' => 'x']);

        try {
            $interpolator->interpolate([
                'a' => '${FIRST}',
                'b' => ['c' => '${KNOWN} and ${SECOND}', 'd' => ['${THIRD}']],
            ]);
            $this->fail('Expected UndefinedVariableException');
        } catch (UndefinedVariableException $exception) {
            $this->assertSame(
                [['FIRST', 'a'], ['SECOND', 'b.c'], ['THIRD', 'b.d[0]']],
                $exception->references,
            );
            $this->assertStringContainsString('3 undefined variables', $exception->getMessage());
        }
    }

    public function testNeverRendersAnEmptyStringOrTheLiteralPlaceholder(): void
    {
        $interpolator = new EnvVarInterpolator([]);

        $this->expectException(UndefinedVariableException::class);

        $interpolator->interpolateString('${MISSING}', 'x');
    }

    /** An empty value is what compose passes through for an unset host variable. It is not a value. */
    public function testAnEmptyValueCountsAsUnset(): void
    {
        $interpolator = new EnvVarInterpolator(['EMPTY' => '', 'NOT_SCALAR' => ['x']]);

        $this->assertFalse($interpolator->has('EMPTY'));
        $this->assertFalse($interpolator->has('NOT_SCALAR'));

        $this->expectException(UndefinedVariableException::class);

        $interpolator->interpolateString('${EMPTY}', 'x');
    }

    public function testPathPrefixIsReportedForASubtree(): void
    {
        $interpolator = new EnvVarInterpolator([]);

        try {
            $interpolator->interpolate(['FOO' => '${BAR}'], 'application_orchestration.application.environment_vars');
            $this->fail('Expected UndefinedVariableException');
        } catch (UndefinedVariableException $exception) {
            $this->assertSame([['BAR', 'application_orchestration.application.environment_vars.FOO']], $exception->references);
        }
    }

    // ------------------------------------------------------------------ sources

    public function testProcessEnvironmentWinsOverTheFallback(): void
    {
        putenv(self::ENV . '=from-env');

        $interpolator = EnvVarInterpolator::fromProcessEnvironment([
            self::ENV     => 'from-fallback',
            'ONLY_HERE'   => 'fallback-only',
        ]);

        $this->assertSame('from-env', $interpolator->interpolateString('${' . self::ENV . '}', 'x'));
        $this->assertSame('fallback-only', $interpolator->interpolateString('${ONLY_HERE}', 'x'));
    }

    /** `MYSQL_HOST=` from compose must not shadow a YAML constant. */
    public function testAnEmptyEnvironmentVariableFallsThroughToTheFallback(): void
    {
        putenv(self::ENV . '_EMPTY=');

        $interpolator = EnvVarInterpolator::fromProcessEnvironment([self::ENV . '_EMPTY' => 'from-fallback']);

        $this->assertSame('from-fallback', $interpolator->interpolateString('${' . self::ENV . '_EMPTY}', 'x'));
    }

    public function testFallbackValuesAreCastToString(): void
    {
        $interpolator = new EnvVarInterpolator(['PORT' => 3306, 'FLAG' => true]);

        $this->assertSame('port=3306 flag=1', $interpolator->interpolateString('port=${PORT} flag=${FLAG}', 'x'));
    }

    // ------------------------------------------------------------------ skipped paths

    public function testASkippedSubtreeIsLeftUntouchedEvenWhenItReferencesUndefinedVariables(): void
    {
        $interpolator = new EnvVarInterpolator(['VAR' => 'value'], ['*.plans.*.steps', '*.plans.*.*_steps']);

        $plans = [
            'default' => [
                'steps'           => ['retry' => 'echo "waiting (${attempt})"', 'x' => ['command' => 'echo ${VAR}']],
                'preflight_steps' => ['check' => 'test -n "${UNDEFINED}"'],
                'comment'         => 'plan for ${VAR}',
            ],
        ];

        $config = $interpolator->interpolate(['deploy' => ['plans' => $plans]]);

        $this->assertSame($plans['default']['steps'], $config['deploy']['plans']['default']['steps']);
        $this->assertSame($plans['default']['preflight_steps'], $config['deploy']['plans']['default']['preflight_steps']);
        $this->assertSame('plan for value', $config['deploy']['plans']['default']['comment']);
    }

    // ------------------------------------------------------------------ paths

    #[DataProvider('paths')]
    public function testChildPathSpellsKeysTheWaySchemaErrorsDo(string $path, string|int $key, string $expected): void
    {
        $this->assertSame($expected, EnvVarInterpolator::childPath($path, $key));
    }

    /** @return iterable<string, array{string, string|int, string}> */
    public static function paths(): iterable
    {
        yield 'root identifier'    => ['', 'database', 'database'];
        yield 'nested identifier'  => ['database', 'adapters', 'database.adapters'];
        yield 'dashes and digits'  => ['a', 'orm-default2', 'a.orm-default2'];
        yield 'file name'          => ['skeleton.files', 'config/autoload/doctrine.local.php', 'skeleton.files[config/autoload/doctrine.local.php]'];
        yield 'list index'         => ['targets', 0, 'targets[0]'];
        yield 'root file name'     => ['', 'a.b', '[a.b]'];
    }
}
