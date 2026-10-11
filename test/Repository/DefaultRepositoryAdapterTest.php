<?php

declare(strict_types=1);

namespace ConductorCoreTest\Repository;

use ConductorCore\Exception\DomainException;
use ConductorCore\Repository\DefaultRepositoryAdapter;
use ConductorCore\Repository\DefaultRepositoryAdapterFactory;
use ConductorCore\Repository\RepositoryAdapterInterface;
use ConductorCore\Repository\RepositoryAdapterManager;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;

final class DefaultRepositoryAdapterTest extends TestCase
{
    public function testResolvesTheConfiguredDefaultOnFirstCall(): void
    {
        $container = new ServiceManager([
            'services'  => ['config' => ['vcs' => ['default_adapter' => 'hg', 'adapters' => ['hg' => ['class' => 'test.hg']]]]],
            'factories' => [
                'test.hg'                        => static fn() => new class implements RepositoryAdapterInterface {
                    public array $calls = [];
                    public function setRepoUrl(string $repoUrl): void { $this->calls[] = "url:$repoUrl"; }
                    public function setPath(string $path): void { $this->calls[] = "path:$path"; }
                    public function checkout(string $repoReference, bool $shallow = false): void { $this->calls[] = "checkout:$repoReference"; }
                    public function isClean(): bool { return true; }
                    public function pull(): void { }
                    public function stash(string $message): void { }
                },
                RepositoryAdapterManager::class  => \ConductorCore\Repository\RepositoryAdapterManagerFactory::class,
                RepositoryAdapterInterface::class => DefaultRepositoryAdapterFactory::class,
            ],
        ]);

        $adapter = $container->get(RepositoryAdapterInterface::class);
        $this->assertInstanceOf(DefaultRepositoryAdapter::class, $adapter);

        $adapter->setRepoUrl('git@example.test:app.git');
        $adapter->setPath('/app');
        $adapter->checkout('main');
        $this->assertTrue($adapter->isClean());
    }

    public function testAMissingDefaultAdapterIsRefusedOnFirstCallNotAtConstruction(): void
    {
        $container = new ServiceManager([
            'services'  => ['config' => []],
            'factories' => [
                RepositoryAdapterManager::class   => \ConductorCore\Repository\RepositoryAdapterManagerFactory::class,
                RepositoryAdapterInterface::class => DefaultRepositoryAdapterFactory::class,
            ],
        ]);

        // No vcs config at all: the default name is `git`, and building the proxy does not need it.
        $adapter = $container->get(RepositoryAdapterInterface::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('VCS adapter "git" is not configured (vcs.adapters.git)');
        $adapter->isClean();
    }
}
