<?php

namespace ConductorCoreTest\Shell;

use ConductorCore\Shell\SecretRedactor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SecretRedactorTest extends TestCase
{
    private const PASSWORD = "s3cr3t'pa ss";

    /** @return iterable<string, array{string, string}> */
    public static function commandsWithAPassword(): iterable
    {
        $quoted = escapeshellarg(self::PASSWORD);

        yield 'mysql client, attached' => [
            "mysql --skip-column-names -e \"SHOW TABLES from `db`;\" -h 'db' -P '3306' -u 'app' -p$quoted --loose-ssl",
            "mysql --skip-column-names -e \"SHOW TABLES from `db`;\" -h 'db' -P '3306' -u 'app' -p*** --loose-ssl",
        ];
        yield 'mydumper, separate word' => [
            "mydumper --database 'db' -h 'db' -P '3306' -u 'app' -p $quoted --ssl",
            "mydumper --database 'db' -h 'db' -P '3306' -u 'app' -p *** --ssl",
        ];
        yield 'myloader after a pipeline stage' => [
            "cd /tmp && myloader -u 'app' -p $quoted 2> >(tee log >&2)",
            "cd /tmp && myloader -u 'app' -p *** 2> >(tee log >&2)",
        ];
        yield 'mysqldump piped to gzip' => [
            "(mysqldump 'db' -u 'app' -p$quoted --no-data) | gzip -9 > 'db.sql.gz'",
            "(mysqldump 'db' -u 'app' -p*** --no-data) | gzip -9 > 'db.sql.gz'",
        ];
        yield 'mysqlimport, long option with a space' => [
            "mysqlimport 'db' --local --password $quoted a.txt",
            "mysqlimport 'db' --local --password *** a.txt",
        ];
        yield 'mariadb client, unquoted' => [
            'mariadb -uapp -phunter2 db',
            'mariadb -uapp -p*** db',
        ];
        yield 'long option with equals, any command' => [
            "docker login --password=$quoted registry",
            'docker login --password=*** registry',
        ];
        yield 'MYSQL_PWD assignment' => [
            "MYSQL_PWD=$quoted mysql -u 'app'",
            "MYSQL_PWD=*** mysql -u 'app'",
        ];
        yield 'MYSQL_PWD in an environment dump' => [
            "HOME=/home/app\nMYSQL_PWD=hunter2\nPATH=/usr/bin\n",
            "HOME=/home/app\nMYSQL_PWD=***\nPATH=/usr/bin\n",
        ];
    }

    #[DataProvider('commandsWithAPassword')]
    public function testMasksThePassword(string $command, string $expected): void
    {
        $this->assertSame($expected, SecretRedactor::redact($command));
    }

    /** `-p` means something else to most commands, so only a mysql-family invocation is touched. */
    public function testLeavesOtherCommandsAlone(): void
    {
        $command = "mkdir -p '/var/www/releases/1' && ssh -p 2222 host && cp -p a b && rm -rf 'db'";

        $this->assertSame($command, SecretRedactor::redact($command));
    }

    public function testLeavesAPasswordlessMysqlCommandAlone(): void
    {
        $command = "mysql --skip-column-names --silent -e \"SHOW TABLES from `db`;\" -h 'db' -P '3306' -u 'app' --ssl";

        $this->assertSame($command, SecretRedactor::redact($command));
    }

    /** The mysql invocation ends at the pipe, so a `-p` in the next command is not taken for one. */
    public function testStopsAtTheEndOfTheMysqlInvocation(): void
    {
        $this->assertSame(
            "mysql -u 'app' -p*** db | mkdir -p '/tmp/x'",
            SecretRedactor::redact("mysql -u 'app' -p'pw' db | mkdir -p '/tmp/x'")
        );
    }
}
