<?php

namespace ConductorCoreTest;

use ConductorCore\Exception;
use ConductorCore\Shell\Adapter\LocalShellAdapter;
use PHPUnit\Framework\TestCase;

class LocalAdapterTest extends TestCase
{
    /**
     * @var LocalShellAdapter
     */
    private $adapter;

    public function setUp()
    {
        $this->adapter = new LocalShellAdapter();
    }

    public function testIsCallableValidCommand()
    {
        $this->assertTrue($this->adapter->isCallable('ls'));
    }

    public function testIsCallableInvalidCommand()
    {
        $this->assertFalse($this->adapter->isCallable('badcommand'));
    }

    public function testIsCallableThrowsExceptionWhenHostsGiven()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->adapter->isCallable('anycommand', ['anyhost']);
    }

    public function testRunShellCommand()
    {
        $this->assertInternalType('string', $this->adapter->runShellCommand('ls'));
    }

    public function testRunShellCommandThrowsExceptionOnError()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->adapter->runShellCommand('badcommand');
    }

    public function testRunShellCommandThrowsExceptionWhenHostsGiven()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->adapter->runShellCommand('ls', ['anyhost']);
    }
}
