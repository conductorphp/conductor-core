<?php

declare(strict_types=1);

namespace ConductorCoreTest\Repository;

use ConductorCore\Exception\DomainException;
use ConductorCore\Repository\RepositoryAdapterInterface;
use ConductorCore\Repository\RepositoryAdapterManager;
use ConductorCore\Repository\RepositoryAdapterManagerFactory;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use stdClass;

final class RepositoryAdapterManagerTest extends TestCase
{
    /** A stateful adapter, like the real one: the test checks each caller gets its own copy. */
    private static function adapter(): RepositoryAdapterInterface
    {
        return new class implements RepositoryAdapterInterface {
            public static int $built = 0;
            public string $path = '';

            public function __construct()
            {
                self::$built++;
            }

            public function setRepoUrl(string $repoUrl): void
            {
            }

            public function setPath(string $path): void
            {
                $this->path = $path;
            }

            public function checkout(string $repoReference, bool $shallow = false): void
            {
            }

            public function isClean(): bool
            {
                return true;
            }

            public function pull(): void
            {
            }

            public function stash(string $message): void
            {
            }
        };
    }

    private function manager(array $services, array $definitions): RepositoryAdapterManager
    {
        return new RepositoryAdapterManager(new ServiceManager(['factories' => $services]), $definitions);
    }

    public function testBuildsOnFirstRequestAndHandsOutClones(): void
    {
        $built   = 0;
        $manager = $this->manager(
            ['test.git' => static function () use (&$built) { $built++; return self::adapter(); }],
            ['git' => ['class' => 'test.git']],
        );

        $this->assertSame(['git'], $manager->getAdapterNames());
        $this->assertTrue($manager->hasAdapter('git'));
        $this->assertSame(0, $built, 'nothing is built until an adapter is requested');

        $first = $manager->getAdapter('git');
        $first->setPath('/one');
        $second = $manager->getAdapter('git');

        $this->assertSame(1, $built, 'the prototype is built once');
        $this->assertNotSame($first, $second);
        $this->assertSame('', $second->path, 'each caller gets its own copy to mutate');
    }

    public function testAnUnconfiguredAdapterIsRefusedNamingTheConfigKey(): void
    {
        $manager = $this->manager([], []);

        $this->assertFalse($manager->hasAdapter('git'));
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('VCS adapter "git" is not configured (vcs.adapters.git). Configured adapters: none.');
        $manager->getAdapter('git');
    }

    public function testAClassThatIsNotAnAdapterIsADomainError(): void
    {
        $manager = $this->manager(
            ['test.thing' => static fn() => new stdClass()],
            ['git' => ['class' => 'test.thing']],
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('vcs.adapters.git names test.thing, but the container returned stdClass');
        $manager->getAdapter('git');
    }

    public function testARequirementTheHostLacksIsRefusedWhenTheAdapterIsBuilt(): void
    {
        $shell = $this->createStub(\ConductorCore\Shell\Adapter\ShellAdapterInterface::class);
        $shell->method('isCallable')->willReturn(false);

        $container = new ServiceManager([
            'services'  => [\ConductorCore\Shell\Adapter\ShellAdapterInterface::class => $shell],
            'factories' => [
                \ConductorCore\Runtime\RequirementChecker::class => \ConductorCore\Runtime\RequirementCheckerFactory::class,
                'test.git' => static fn() => new class implements RepositoryAdapterInterface, \ConductorCore\Runtime\RuntimeRequirementsInterface {
                    public function runtimeRequirements(): array { return [\ConductorCore\Runtime\Requirement::binary('git')]; }
                    public function setRepoUrl(string $repoUrl): void { }
                    public function setPath(string $path): void { }
                    public function checkout(string $repoReference, bool $shallow = false): void { }
                    public function isClean(): bool { return true; }
                    public function pull(): void { }
                    public function stash(string $message): void { }
                },
            ],
        ]);
        $manager = new RepositoryAdapterManager($container, ['git' => ['class' => 'test.git']]);

        $this->expectException(\ConductorCore\Exception\RuntimeRequirementException::class);
        $this->expectExceptionMessage('VCS adapter "git" (vcs.adapters.git) needs command "git".');
        $manager->getAdapter('git');
    }

    public function testFactoryResolvesAliasesAndRejectsADanglingOne(): void
    {
        $factory   = new RepositoryAdapterManagerFactory();
        $container = new ServiceManager(['services' => ['config' => ['vcs' => ['adapters' => [
            'git'     => ['class' => 'test.git'],
            'default' => ['alias' => 'git'],
        ]]]]]);

        $this->assertSame(['git', 'default'], $factory($container, RepositoryAdapterManager::class)->getAdapterNames());

        $container = new ServiceManager(['services' => ['config' => ['vcs' => ['adapters' => [
            'default' => ['alias' => 'missing'],
        ]]]]]);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('VCS adapter "default" aliases adapter "missing" which does not exist');
        $factory($container, RepositoryAdapterManager::class);
    }
}
