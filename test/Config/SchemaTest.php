<?php

namespace ConductorCoreTest\Config;

use ConductorCore\Config\ParsesConfigTrait;
use ConductorCore\Config\Schema\SchemaBuilder;
use ConductorCore\Config\Schema\SchemaInterface;
use ConductorCore\Exception\InvalidConfigException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * CTAP-1630. The config schema layer.
 *
 * The behaviors worth pinning are the ones that make a schema safer than `?? null`: telling an absent
 * key from an explicit null, refusing coercions that would turn a typo into a plausible value,
 * reporting every problem at once instead of the first, and naming the path so a reader knows which
 * file to open.
 */
class SchemaTest extends TestCase
{
    private SchemaBuilder $sb;

    protected function setUp(): void
    {
        $this->sb = new SchemaBuilder();
    }

    // ------------------------------------------------------------------ presence and defaults

    /**
     * The distinction `?? null` cannot make. An absent key takes its default; a key set explicitly to
     * null is a deliberate null, and is an error unless the node is nullable.
     */
    public function testAnAbsentKeyTakesItsDefaultButAnExplicitNullIsRejected(): void
    {
        $schema = $this->sb->map(['branch' => $this->sb->string()->default('master')]);

        $this->assertSame(['branch' => 'master'], $schema->parse([])->value);

        $result = $schema->parse(['branch' => null]);
        $this->assertFalse($result->isValid());
        $this->assertSame(['branch: must not be null'], $result->errors);
    }

    public function testANullableNodeKeepsAnExplicitNull(): void
    {
        $schema = $this->sb->map(['branch' => $this->sb->string()->nullable()->default('master')]);

        $this->assertSame(['branch' => null], $schema->parse(['branch' => null])->value);
    }

    /** Absent with no default stays absent, so a Config can tell "unset" from "empty". */
    public function testAnAbsentKeyWithNoDefaultIsOmittedRatherThanNulled(): void
    {
        $schema = $this->sb->map(['branch' => $this->sb->string()]);

        $this->assertSame([], $schema->parse([])->value);
    }

    public function testARequiredKeyIsReportedByPath(): void
    {
        $schema = $this->sb->map([
            'app' => $this->sb->map(['name' => $this->sb->string()->required()]),
        ]);

        $this->assertSame(['app.name: is required'], $schema->parse(['app' => []])->errors);
    }

    /** `empty()` rejects 0 and '0'; a required check must not. */
    public function testZeroSatisfiesARequiredCheck(): void
    {
        $schema = $this->sb->map(['retries' => $this->sb->int()->required()]);

        $result = $schema->parse(['retries' => 0]);
        $this->assertTrue($result->isValid());
        $this->assertSame(['retries' => 0], $result->value);
    }

    // ------------------------------------------------------------------ scalars

    #[DataProvider('acceptedScalars')]
    public function testScalarsCoerceTheSpellingsConfigActuallyCarries(string $type, mixed $given, mixed $expected): void
    {
        $schema = $this->sb->map(['v' => $this->sb->{$type}()]);

        $result = $schema->parse(['v' => $given]);
        $this->assertTrue($result->isValid(), implode('; ', $result->errors));
        $this->assertSame($expected, $result->value['v']);
    }

    /** @return iterable<string, array{string, mixed, mixed}> */
    public static function acceptedScalars(): iterable
    {
        yield 'int stays int'         => ['int', 22, 22];
        yield 'numeric string to int' => ['int', '22', 22];
        yield 'int to string'         => ['string', 22, '22'];
        yield 'bool stays bool'       => ['bool', true, true];
        yield 'string "true"'         => ['bool', 'true', true];
        yield 'string "false"'        => ['bool', 'false', false];
        yield 'string "yes"'          => ['bool', 'yes', true];
        yield 'int 0 to bool'         => ['bool', 0, false];
        yield 'int to float'          => ['float', 3, 3.0];
    }

    /**
     * The coercions it must REFUSE. `(int) 'abc'` is 0 and `(int) '2 servers'` is 2 — a cast would
     * turn a typo into a plausible value and the deploy would run with it.
     */
    #[DataProvider('refusedScalars')]
    public function testScalarsRefuseMeaninglessCoercions(string $type, mixed $given): void
    {
        $schema = $this->sb->map(['v' => $this->sb->{$type}()]);

        $this->assertFalse($schema->parse(['v' => $given])->isValid());
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function refusedScalars(): iterable
    {
        yield 'word as int'      => ['int', 'abc'];
        yield 'partial as int'   => ['int', '2 servers'];
        yield 'float as int'     => ['int', '2.5'];
        yield 'array as string'  => ['string', ['a']];
        yield 'array as int'     => ['int', ['a']];
        yield 'word as bool'     => ['bool', 'maybe'];
        yield 'word as float'    => ['float', 'abc'];
    }

    public function testStringCanTrimAndRejectEmpty(): void
    {
        $trimmed = $this->sb->map(['v' => $this->sb->string()->trim()]);
        $this->assertSame('x', $trimmed->parse(['v' => "  x \n"])->value['v']);

        $notEmpty = $this->sb->map(['v' => $this->sb->string()->notEmpty()]);
        $this->assertSame(['v: must not be empty'], $notEmpty->parse(['v' => ''])->errors);
    }

    // ------------------------------------------------------------------ file modes

    /**
     * The int and string forms of a permission mode need OPPOSITE handling, and both silent wrong
     * answers are easy to produce: `(int) '0750'` is 750 decimal (mode 1366), and `octdec('488')` is
     * 4 because octdec ignores the digits 8 and 9.
     */
    #[DataProvider('fileModes')]
    public function testFileModeNormalizesToTheIntChmodWants(mixed $given, int $expected): void
    {
        $schema = $this->sb->map(['mode' => $this->sb->fileMode()]);

        $result = $schema->parse(['mode' => $given]);
        $this->assertTrue($result->isValid(), implode('; ', $result->errors));
        $this->assertSame($expected, $result->value['mode']);
    }

    /** @return iterable<string, array{mixed, int}> */
    public static function fileModes(): iterable
    {
        // 0750 as a PHP octal literal IS 488 decimal — already the mode, must not be converted.
        yield 'octal int literal'  => [0750, 488];
        yield 'octal string'       => ['0750', 488];
        yield 'octal string no 0'  => ['750', 488];
        yield 'file mode literal'  => [0640, 416];
        yield 'file mode string'   => ['0640', 416];
    }

    #[DataProvider('badFileModes')]
    public function testFileModeRejectsWhatIsNotAMode(mixed $given): void
    {
        $schema = $this->sb->map(['mode' => $this->sb->fileMode()]);

        $this->assertFalse($schema->parse(['mode' => $given])->isValid());
    }

    /** @return iterable<string, array{mixed}> */
    public static function badFileModes(): iterable
    {
        yield 'digit out of octal range' => ['0798'];
        yield 'not a number'             => ['rwxr-x---'];
        // '07500' is NOT here: that is a leading zero plus 7500, a valid four-digit mode
        // (setuid/setgid/sticky plus r-x------). Six characters is past anything meaningful.
        yield 'too many digits'          => ['075000'];
        yield 'too few digits'           => ['75'];
        yield 'array'                    => [['0750']];
    }

    // ------------------------------------------------------------------ maps and collections

    /**
     * Unknown keys pass through by default. Conductor merges config from package defaults, platform
     * support packages, the project and the environment, so a section carries keys no one schema
     * describes; dropping them would lose real configuration.
     */
    public function testUnknownKeysArePreservedByDefault(): void
    {
        $schema = $this->sb->map(['known' => $this->sb->string()->default('a')]);

        $this->assertSame(
            ['known' => 'a', 'merged_in' => ['deep' => 1]],
            $schema->parse(['merged_in' => ['deep' => 1]])->value,
        );
    }

    public function testUnknownKeysCanBeRejectedWhereTheShapeIsClosed(): void
    {
        $schema = $this->sb->map(['known' => $this->sb->string()->default('a')])->rejectUnknownKeys();

        $this->assertSame(['unknown key(s): typo'], $schema->parse(['typo' => 1])->errors);
    }

    public function testCollectionValidatesEveryEntryAgainstOneSchema(): void
    {
        $schema = $this->sb->collection($this->sb->map([
            'adapter' => $this->sb->string()->default('default'),
        ]));

        $this->assertSame(
            ['main' => ['adapter' => 'default'], 'reports' => ['adapter' => 'mydumper']],
            $schema->parse(['main' => [], 'reports' => ['adapter' => 'mydumper']])->value,
        );
    }

    public function testCollectionErrorsNameTheOffendingEntry(): void
    {
        $schema = $this->sb->map([
            'databases' => $this->sb->collection($this->sb->map([
                'adapter' => $this->sb->string()->required(),
            ])),
        ]);

        $this->assertSame(
            ['databases.reports.adapter: is required'],
            $schema->parse(['databases' => ['reports' => []]])->errors,
        );
    }

    // ------------------------------------------------------------------ enums, transformers, errors

    public function testEnumListsTheValidOptionsWhenItDoesNotMatch(): void
    {
        $schema = $this->sb->map([
            'file_layout' => $this->sb->enum(['default', 'blue_green']),
        ]);

        $this->assertSame(
            ["file_layout: must be one of: 'default', 'blue_green', got 'blugreen'"],
            $schema->parse(['file_layout' => 'blugreen'])->errors,
        );
    }

    /** A transformer is how a nested config tree becomes a nested DTO instead of a nested array. */
    public function testATransformerHydratesASubObject(): void
    {
        $schema = $this->sb->map([
            'ssh' => $this->sb->map([
                'port'     => $this->sb->int()->default(22),
                'username' => $this->sb->string()->default('webuser'),
            ])->withTransformer(static fn(array $v): object => new class ($v['port'], $v['username']) {
                public function __construct(public readonly int $port, public readonly string $username)
                {
                }
            }),
        ]);

        $ssh = $schema->parse(['ssh' => ['port' => '2222']])->value['ssh'];
        $this->assertSame(2222, $ssh->port);
        $this->assertSame('webuser', $ssh->username);
    }

    /** A transformer must not run on invalid input — it would receive a shape it cannot handle. */
    public function testATransformerDoesNotRunWhenValidationFailed(): void
    {
        $schema = $this->sb->map([
            'port' => $this->sb->int()->withTransformer(static function (): never {
                throw new \LogicException('transformer must not run');
            }),
        ]);

        $this->assertFalse($schema->parse(['port' => 'abc'])->isValid());
    }

    /** Every problem at once: fix the config in one pass, not one redeploy per mistake. */
    public function testAllErrorsAreCollectedNotJustTheFirst(): void
    {
        $schema = $this->sb->map([
            'app_name'    => $this->sb->string()->required(),
            'repo_url'    => $this->sb->string()->required(),
            'file_layout' => $this->sb->enum(['default', 'blue_green']),
            'dir_mode'    => $this->sb->fileMode(),
        ]);

        $errors = $schema->parse(['file_layout' => 'nope', 'dir_mode' => 'rwx'])->errors;

        $this->assertCount(4, $errors);
        $this->assertStringContainsString('app_name: is required', $errors[0]);
        $this->assertStringContainsString('repo_url: is required', $errors[1]);
    }

    // ------------------------------------------------------------------ ParsesConfigTrait

    public function testParseConfigReturnsAnEmptyArrayForAbsentConfig(): void
    {
        $this->assertSame([], $this->consumer()->parse(null));
    }

    public function testParseConfigThrowsNamingThePathAndEveryProblem(): void
    {
        try {
            $this->consumer()->parse(['file_layout' => 'nope']);
            $this->fail('Expected InvalidConfigException');
        } catch (InvalidConfigException $exception) {
            $message = $exception->getMessage();
            $this->assertStringContainsString('Invalid configuration for "some_package"', $message);
            $this->assertStringContainsString('2 problems', $message);
            $this->assertStringContainsString('app_name: is required', $message);
            $this->assertStringContainsString('file_layout: must be one of', $message);
        }
    }

    public function testParseConfigReturnsTheTransformedValueWhenValid(): void
    {
        $this->assertSame(
            ['app_name' => 'shop', 'file_layout' => 'default'],
            $this->consumer()->parse(['app_name' => 'shop']),
        );
    }

    private function consumer(): object
    {
        return new class ($this->sb) {
            use ParsesConfigTrait;

            public function __construct(private readonly SchemaBuilder $sb)
            {
            }

            /** @return array<string, mixed> */
            public function parse(?array $config): array
            {
                return $this->parseConfig($config, $this->schema(), 'some_package');
            }

            private function schema(): SchemaInterface
            {
                return $this->sb->map([
                    'app_name'    => $this->sb->string()->required(),
                    'file_layout' => $this->sb->enum(['default', 'blue_green'])->default('default'),
                ]);
            }
        };
    }
}
