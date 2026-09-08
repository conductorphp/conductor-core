<?php

declare(strict_types=1);

namespace ConductorCore\Exception;

/**
 * Config did not satisfy its schema.
 *
 * Separate from {@see RuntimeException} so a caller can tell "your configuration is wrong, here is
 * what to fix" from "something failed while running", and so a console command can render it as
 * guidance rather than a stack trace.
 */
class InvalidConfigException extends RuntimeException
{
}
