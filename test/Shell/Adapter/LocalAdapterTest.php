<?php

namespace ConductorSshSupportTest;

use ConductorSshSupport\Shell\Adapter\SshAdapter;
use ConductorSshSupport\Exception;
use phpseclib\Crypt\RSA;
use phpseclib\Net\SSH2;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ProphecyInterface;

class SshAdapterTest extends TestCase
{
    /**
     * @var ProphecyInterface
     */
    private $client;

    public function setUp()
    {
        $client = $this->prophesize(SSH2::class);
        $client->login(Argument::type('string'), Argument::type('string'))->willReturn(true);
        $client->exec(Argument::type('string'))->willReturn('');
        $client->getExitStatus()->willReturn(0);
        $client->getStdError()->willReturn('Standard error');
        $this->client = $client;
    }

    public function testThrowsExceptionWithBadLogin()
    {
        $this->client->login(Argument::type('string'), Argument::type('string'))->willReturn(false);
        /** @var SSH2 $client */
        $client = $this->client->reveal();

        $this->expectException(Exception\RuntimeException::class);
        $adapter = new SshAdapter($client, 'anyusername', null, 'anypassword');
        $adapter->isCallable('anycommand');
    }

    public function testKeyLogin()
    {
        $this->client->login(Argument::type('string'), Argument::type(RSA::class))->willReturn(true);
        /** @var SSH2 $client */
        $client = $this->client->reveal();

        $adapter = new SshAdapter($client, 'anyusername', 'anykey');
        $this->assertTrue($adapter->isCallable('anycommand'));
    }

    public function testKeyPasswordLogin()
    {
        $this->client->login(Argument::type('string'), Argument::type(RSA::class))->willReturn(true);
        /** @var SSH2 $client */
        $client = $this->client->reveal();

        $adapter = new SshAdapter($client, 'anyusername', 'anykey', 'anypassword');
        $this->assertTrue($adapter->isCallable('anycommand'));
    }

    public function testPasswordLogin()
    {
        /** @var SSH2 $client */
        $client = $this->client->reveal();
        
        $adapter = new SshAdapter($client, 'anyusername', null, 'anypassword');
        $this->assertTrue($adapter->isCallable('anycommand'));
    }

    public function testIsCallableValidCommand()
    {
        /** @var SSH2 $client */
        $client = $this->client->reveal();

        $adapter = new SshAdapter($client, 'anyusername', null, 'anypassword');
        $this->assertTrue($adapter->isCallable('anycommand'));
    }

    public function testIsCallableInvalidCommand()
    {
        $this->client->getExitStatus()->willReturn(1);
        /** @var SSH2 $client */
        $client = $this->client->reveal();

        $adapter = new SshAdapter($client, 'anyusername', null, 'anypassword');
        $this->assertFalse($adapter->isCallable('anycommand'));
    }

    public function testIsCallableThrowsExceptionWhenHostsGiven()
    {
        /** @var SSH2 $client */
        $client = $this->client->reveal();

        $adapter = new SshAdapter($client, 'anyusername', null, 'anypassword');
        $this->expectException(Exception\RuntimeException::class);
        $adapter->isCallable('anycommand', ['anyhost']);
    }

    public function testRunShellCommand()
    {
        /** @var SSH2 $client */
        $client = $this->client->reveal();

        $adapter = new SshAdapter($client, 'anyusername', null, 'anypassword');
        $this->assertInternalType('string', $adapter->runShellCommand('anycommand'));
    }

    public function testRunShellCommandThrowsExceptionOnError()
    {
        $this->client->getExitStatus()->willReturn(1);
        /** @var SSH2 $client */
        $client = $this->client->reveal();

        $adapter = new SshAdapter($client, 'anyusername', null, 'anypassword');
        $this->expectException(Exception\RuntimeException::class);
        $adapter->runShellCommand('anycommand');
    }

    public function testRunShellCommandThrowsExceptionWhenHostsGiven()
    {
        /** @var SSH2 $client */
        $client = $this->client->reveal();

        $adapter = new SshAdapter($client, 'anyusername', null, 'anypassword');
        $this->expectException(Exception\RuntimeException::class);
        $adapter->runShellCommand('anycommand', ['anyhost']);
    }
}
