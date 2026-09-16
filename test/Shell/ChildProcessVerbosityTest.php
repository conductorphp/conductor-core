<?php

namespace ConductorCoreTest\Shell;

use ConductorCore\Shell\ChildProcessVerbosity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChildProcessVerbosityTest extends TestCase
{
    /** @return iterable<string, array{0: string, 1: string|null}> */
    public static function conductorLevels(): iterable
    {
        yield 'quiet passes through' => ['-1', '-1'];
        yield 'normal runs the child at its default' => ['0', null];
        yield '-v runs the child at its default' => ['1', null];
        yield '-vv runs the child at its default' => ['2', null];
        yield '-vvv passes through' => ['3', '3'];
        yield 'anything above debug passes through' => ['4', '4'];
        yield 'garbage counts as normal' => ['loud', null];
    }

    #[DataProvider('conductorLevels')]
    public function testOnlyQuietAndDebugReachTheChild(string $conductorLevel, ?string $childLevel): void
    {
        $environment = ChildProcessVerbosity::forChild([
            'PATH' => '/usr/bin',
            ChildProcessVerbosity::ENV_VAR => $conductorLevel,
        ]);

        $this->assertSame('/usr/bin', $environment['PATH'], 'unrelated variables must survive');
        if (null === $childLevel) {
            $this->assertArrayNotHasKey(ChildProcessVerbosity::ENV_VAR, $environment);
        } else {
            $this->assertSame($childLevel, $environment[ChildProcessVerbosity::ENV_VAR]);
        }
    }

    public function testAnEnvironmentWithoutTheVariableIsReturnedUnchanged(): void
    {
        $environment = ['PATH' => '/usr/bin', 'HOME' => '/home/app'];

        $this->assertSame($environment, ChildProcessVerbosity::forChild($environment));
    }
}
