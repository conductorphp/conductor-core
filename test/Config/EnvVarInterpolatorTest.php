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
 * CTAP-1984 adds `${VAR:-default}` and rejects every other shell form instead of passing it through.
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

    /** Text that is not `${NAME…}` at all is not a placeholder and passes through untouched. */
    #[DataProvider('notPlaceholders')]
    public function testTextThatIsNotAPlaceholderPassesThrough(string $value): void
    {
        $interpolator = new EnvVarInterpolator([]);

        $this->assertSame($value, $interpolator->interpolateString($value, 'x'));
    }

    /** @return iterable<string, array{string}> */
    public static function notPlaceholders(): iterable
    {
        yield 'no braces' => ['$VAR'];
        yield 'bad name'  => ['${9VAR}'];
        yield 'empty'     => ['${}'];
        yield 'price'     => ['costs $5'];
        yield 'unclosed'  => ['${VAR'];
    }

    // ------------------------------------------------------------------ defaults (CTAP-1984)

    /** The four cases from the ticket: the default fills an unset variable and yields to a set one. */
    public function testADefaultAppliesWhenTheVariableIsUnsetAndYieldsWhenItIsSet(): void
    {
        $unset = new EnvVarInterpolator([]);
        $set   = new EnvVarInterpolator(['DATABASE_PORT' => '3307']);

        $this->assertSame('3306', $unset->interpolateString('${DATABASE_PORT:-3306}', 'x'));
        $this->assertSame('3307', $set->interpolateString('${DATABASE_PORT:-3306}', 'x'));
        $this->assertSame('3307', $set->interpolateString('${DATABASE_PORT}', 'x'));

        $this->expectException(UndefinedVariableException::class);

        $unset->interpolateString('${DATABASE_PORT}', 'x');
    }

    /** Empty is unset here as everywhere else in this class, and as the shell's `:-` treats it. */
    public function testADefaultAppliesWhenTheVariableIsEmpty(): void
    {
        $interpolator = new EnvVarInterpolator(['RABBITMQ_VIRTUAL_HOST' => '']);

        $this->assertSame('/', $interpolator->interpolateString('${RABBITMQ_VIRTUAL_HOST:-/}', 'x'));
    }

    /** `${NAME:-}` is how a config says a value may legitimately be blank. */
    public function testAnEmptyDefaultRendersAnEmptyString(): void
    {
        $interpolator = new EnvVarInterpolator([]);

        $this->assertSame('', $interpolator->interpolateString('${OPTIONAL:-}', 'x'));
        $this->assertSame('a=,b=1', $interpolator->interpolateString('a=${OPTIONAL:-},b=1', 'x'));
    }

    /** The default is literal text to the closing brace: spaces, colons, dashes, dots and slashes included. */
    #[DataProvider('literalDefaults')]
    public function testTheDefaultIsLiteralTextUpToTheClosingBrace(string $default): void
    {
        $interpolator = new EnvVarInterpolator([]);

        $this->assertSame($default, $interpolator->interpolateString('${VAR:-' . $default . '}', 'x'));
    }

    /** @return iterable<string, array{string}> */
    public static function literalDefaults(): iterable
    {
        yield 'url'           => ['https://example.test:8443/path'];
        yield 'dash and colon' => ['-:-'];
        yield 'spaces'        => ['two words'];
        yield 'dollar'        => ['$5'];
        yield 'equals'        => ['a=b'];
    }

    /** A default is data, like a value: what it contains is not expanded again. */
    public function testTheDefaultIsNotExpandedAgain(): void
    {
        $interpolator = new EnvVarInterpolator(['INNER' => 'never']);

        $this->assertSame('$INNER', $interpolator->interpolateString('${OUTER:-$INNER}', 'x'));
    }

    public function testADefaultAndAValueInTheSameStringResolveIndependently(): void
    {
        $interpolator = new EnvVarInterpolator(['HOST' => 'db']);

        $this->assertSame('db:3306', $interpolator->interpolateString('${HOST:-localhost}:${PORT:-3306}', 'x'));
    }

    /** Filter first, then default; the filter applies to whichever value wins. */
    public function testTheFilterAppliesToTheDefaultWhenItWins(): void
    {
        $unset = new EnvVarInterpolator([]);
        $set   = new EnvVarInterpolator(['KEY' => base64_encode('from-env')]);

        $placeholder = '${KEY|b64decode:-' . base64_encode('from-default') . '}';

        $this->assertSame('from-default', $unset->interpolateString($placeholder, 'x'));
        $this->assertSame('from-env', $set->interpolateString($placeholder, 'x'));
        $this->assertSame('', $unset->interpolateString('${KEY|b64decode:-}', 'x'));
    }

    /** A default that is not base64 is caught before the operator ever leaves the variable unset. */
    public function testAFilterThatRejectsTheDefaultFailsLoudly(): void
    {
        $interpolator = new EnvVarInterpolator([]);

        $this->expectException(InvalidPlaceholderException::class);
        $this->expectExceptionMessage('Filter "b64decode" failed for variable "KEY"');

        $interpolator->interpolateString('${KEY|b64decode:-not*base64}', 'x');
    }

    public function testAnUnknownFilterWithADefaultIsStillAnError(): void
    {
        $interpolator = new EnvVarInterpolator([]);

        $this->expectException(InvalidPlaceholderException::class);
        $this->expectExceptionMessage('Unknown filter "rot13"');

        $interpolator->interpolateString('${KEY|rot13:-x}', 'x');
    }

    /** A defaulted placeholder is never undefined, so it does not show up in the report. */
    public function testADefaultedPlaceholderIsNotReportedAsUndefined(): void
    {
        $interpolator = new EnvVarInterpolator([]);

        try {
            $interpolator->interpolate(['a' => '${PORT:-3306}', 'b' => '${MISSING}']);
            $this->fail('Expected UndefinedVariableException');
        } catch (UndefinedVariableException $exception) {
            $this->assertSame([['MISSING', 'b']], $exception->references);
            $this->assertStringContainsString('${NAME:-default}', $exception->getMessage());
        }
    }

    public function testDefaultsFillAcrossTheTree(): void
    {
        $interpolator = new EnvVarInterpolator(['MYSQL_HOST' => 'db.internal']);

        $config = $interpolator->interpolate([
            'database' => ['adapters' => ['default' => ['arguments' => [
                'host' => '${MYSQL_HOST:-localhost}',
                'port' => '${MYSQL_PORT:-3306}',
            ]]]],
        ]);

        $this->assertSame(
            ['host' => 'db.internal', 'port' => '3306'],
            $config['database']['adapters']['default']['arguments'],
        );
    }

    // ------------------------------------------------------------------ rejected forms (CTAP-1984)

    /**
     * The actual bug: every one of these used to pass through as literal text with no error, whether or
     * not the variable was set. Now each fails the load naming the variable and the config path.
     */
    #[DataProvider('unsupportedPlaceholders')]
    public function testAnUnsupportedShellOperatorFailsNamingTheVariableAndPath(
        string $placeholder,
        string $reason,
        ?string $reported = null,
    ): void {
        // The pattern stops at the first `}`, so a nested `${VAR:-${OTHER}}` is reported up to there.
        $reported ??= $placeholder;

        foreach ([new EnvVarInterpolator([]), new EnvVarInterpolator(['VAR' => 'set'])] as $interpolator) {
            try {
                $interpolator->interpolate(['database' => ['port' => "prefix {$placeholder} suffix"]]);
                $this->fail("Expected InvalidPlaceholderException for {$placeholder}");
            } catch (InvalidPlaceholderException $exception) {
                $this->assertStringContainsString("Unsupported placeholder \"{$reported}\"", $exception->getMessage());
                $this->assertStringContainsString('variable "VAR"', $exception->getMessage());
                $this->assertStringContainsString('at "database.port"', $exception->getMessage());
                $this->assertStringContainsString($reason, $exception->getMessage());
                $this->assertStringContainsString(InvalidPlaceholderException::SUPPORTED_FORMS, $exception->getMessage());
                $this->assertStringContainsString("\"\${$reported}\"", $exception->getMessage());
                $this->assertInstanceOf(InvalidConfigException::class, $exception);
            }
        }
    }

    /** @return iterable<string, array{string, string, 2?: string}> */
    public static function unsupportedPlaceholders(): iterable
    {
        $operators = 'only ":-" is supported';
        $order     = 'the filter goes before the default';
        $nested    = 'cannot contain another placeholder';

        yield 'unset-only default'    => ['${VAR-x}', $operators];
        yield 'assign default'        => ['${VAR:=x}', $operators];
        yield 'required'              => ['${VAR:?}', $operators];
        yield 'required with message' => ['${VAR:?message}', $operators];
        yield 'alternate'             => ['${VAR:+x}', $operators];
        yield 'substring'             => ['${VAR:0:2}', $operators];
        yield 'suffix strip'          => ['${VAR%/}', $operators];
        yield 'empty filter'          => ['${VAR|}', $operators];
        yield 'two filters'           => ['${VAR|b64decode|b64decode}', $operators];
        yield 'space'                 => ['${VAR }', $operators];
        yield 'filter after default'  => ['${VAR:-x|b64decode}', $order];
        yield 'filter twice'          => ['${VAR|b64decode:-x|b64decode}', $order];
        yield 'nested placeholder'    => ['${VAR:-${OTHER}}', $nested, '${VAR:-${OTHER}'];
        yield 'nested with filter'    => ['${VAR|b64decode:-${OTHER}}', $nested, '${VAR|b64decode:-${OTHER}'];
    }

    /** `${#VAR}` has no leading identifier, so it is not a placeholder by the pattern, like `${9VAR}`. */
    public function testALengthExpansionIsNotAPlaceholderAtAll(): void
    {
        $interpolator = new EnvVarInterpolator(['VAR' => 'set']);

        $this->assertSame('${#VAR}', $interpolator->interpolateString('${#VAR}', 'x'));
    }

    /** The escape covers the whole braced text, so a shell form is a literal too, never validated. */
    #[DataProvider('escapedShellForms')]
    public function testAnEscapedShellFormRendersLiterally(string $literal): void
    {
        $interpolator = new EnvVarInterpolator(['VAR' => 'set']);

        $this->assertSame($literal, $interpolator->interpolateString('$' . $literal, 'x'));
    }

    /** @return iterable<string, array{string}> */
    public static function escapedShellForms(): iterable
    {
        yield 'default'  => ['${VAR:-x}'];
        yield 'assign'   => ['${VAR:=x}'];
        yield 'required' => ['${VAR:?}'];
    }

    /** A plan step's `${CONDUCTOR_CRYPT_KEY:-}` and `${attempt}` are the shell's business, as before. */
    public function testShellFormsInASkippedSubtreeAreNeitherFilledNorRejected(): void
    {
        $interpolator = new EnvVarInterpolator(['VAR' => 'set'], ['*.plans.*.steps', '*.plans.*.*_steps']);
        $steps        = [
            'key'   => 'test -n "${CONDUCTOR_CRYPT_KEY:-}"',
            'retry' => 'echo "${attempt:?} of ${VAR:=x} ${VAR:-y}"',
        ];

        $config = $interpolator->interpolate(['deploy' => ['plans' => ['default' => ['steps' => $steps]]]]);

        $this->assertSame($steps, $config['deploy']['plans']['default']['steps']);
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
