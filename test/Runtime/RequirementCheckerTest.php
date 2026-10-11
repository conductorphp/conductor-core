<?php

declare(strict_types=1);

namespace ConductorCoreTest\Runtime;

use ConductorCore\Exception\RuntimeRequirementException;
use ConductorCore\Runtime\Requirement;
use ConductorCore\Runtime\RequirementChecker;
use ConductorCore\Runtime\RuntimeRequirementsInterface;
use ConductorCore\Shell\Adapter\ShellAdapterInterface;
use PHPUnit\Framework\TestCase;
use stdClass;

final class RequirementCheckerTest extends TestCase
{
    /** @param list<string> $available Commands the fake host has */
    private function checker(array $available): RequirementChecker
    {
        $shell = $this->createStub(ShellAdapterInterface::class);
        $shell->method('isCallable')->willReturnCallback(static fn(string $c): bool => in_array($c, $available, true));

        return new RequirementChecker($shell);
    }

    public function testListsEveryMissingRequirementWithItsHint(): void
    {
        $checker = $this->checker(['tar']);

        $missing = $checker->missing([
            Requirement::binary('tar'),
            Requirement::binary('mydumper', 'the mydumper package'),
            Requirement::binary('myloader', 'the mydumper package'),
            Requirement::extension('json'),
            Requirement::extension('definitely_not_loaded_ext'),
        ]);

        $this->assertSame(['mydumper', 'myloader', 'definitely_not_loaded_ext'], array_map(static fn($r) => $r->name, $missing));

        $this->expectException(RuntimeRequirementException::class);
        $this->expectExceptionMessage(
            'database import/export adapter "default" (database.importexport_adapters.default) needs'
            . ' command "mydumper" (the mydumper package), command "myloader" (the mydumper package),'
            . ' PHP extension "definitely_not_loaded_ext". Install it where conductor runs the command, or select another adapter.'
        );
        $checker->assert('database import/export adapter "default" (database.importexport_adapters.default)', [
            Requirement::binary('tar'),
            Requirement::binary('mydumper', 'the mydumper package'),
            Requirement::binary('myloader', 'the mydumper package'),
            Requirement::extension('definitely_not_loaded_ext'),
        ]);
    }

    public function testIsQuietWhenEverythingIsPresent(): void
    {
        $this->checker(['git'])->assert('VCS adapter "git"', [Requirement::binary('git'), Requirement::extension('json')]);
        $this->addToAssertionCount(1);
    }

    public function testAssertUsableChecksOnlyCandidatesThatDeclare(): void
    {
        $checker = $this->checker([]);

        $checker->assertUsable('anything', new stdClass());
        $this->addToAssertionCount(1);

        $declaring = new class implements RuntimeRequirementsInterface {
            public function runtimeRequirements(): array
            {
                return [Requirement::binary('git', 'install git')];
            }
        };
        $this->expectException(RuntimeRequirementException::class);
        $this->expectExceptionMessage('VCS adapter "git" (vcs.adapters.git) needs command "git" (install git).');
        $checker->assertUsable('VCS adapter "git" (vcs.adapters.git)', $declaring);
    }
}
