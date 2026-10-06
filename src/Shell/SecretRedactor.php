<?php

namespace ConductorCore\Shell;

/**
 * Masks passwords in a shell command, or in what one printed, before it is logged or put in an
 * exception message.
 *
 * A failed command's message is its command line, and since CTAP-2006 that message is also logged
 * at ERROR, so a password passed as an argument reached the app log, cron mail and log shipping.
 * Callers should keep secrets off the command line in the first place (the MySQL adapters pass
 * MYSQL_PWD in the environment); this is the net under the ones that do not (CTAP-2218).
 *
 * Masked:
 *
 * - `MYSQL_PWD=…` and `--password=…` anywhere.
 * - `-p…`, `-p …` and `--password …` in a mysql, mariadb, mysqldump, mysqlimport, mydumper or
 *   myloader invocation. Only there, because `-p` means something else to most commands
 *   (`mkdir -p`, `ssh -p`). mydumper and myloader take the value as the next word, the mysql clients
 *   take it attached; a bare `mysql -p db` (prompt for the password) masks the database name, which
 *   is the safe way to be wrong.
 *
 * A value is one shell word, quoted or not, so `-p'it'\''s'` is masked whole. This is a best effort
 * over text, not a parser: it masks too much rather than too little.
 */
final class SecretRedactor
{
    public const MASK = '***';

    /** One shell word: any run of single-quoted, double-quoted, escaped and plain characters. */
    private const WORD = '(?:\'[^\']*\'|"(?:[^"\\\\]|\\\\.)*"|\\\\.|[^\s\'"\\\\;&|<>()])+';

    /** A mysql-family invocation, from the program name to the end of its simple command. */
    private const MYSQL_INVOCATION = '/(?<![\w.-])(?:mysql\w*|mariadb[\w-]*|mydumper|myloader)(?![\w.-])'
        . '(?:\'[^\']*\'|"(?:[^"\\\\]|\\\\.)*"|\\\\.|[^\'"\\\\;&|\n])*/';

    public static function redact(string $text): string
    {
        $text = preg_replace(
            [
                '/(?<![\w])(MYSQL_PWD=)' . self::WORD . '/',
                '/(?<![\w-])(--password=)' . self::WORD . '/',
            ],
            '${1}' . self::MASK,
            $text
        ) ?? $text;

        return preg_replace_callback(
            self::MYSQL_INVOCATION,
            static fn (array $match): string => preg_replace(
                '/(?<=\s)(-p\s*|--password\s+)' . self::WORD . '/',
                '${1}' . self::MASK,
                $match[0]
            ) ?? $match[0],
            $text
        ) ?? $text;
    }
}
