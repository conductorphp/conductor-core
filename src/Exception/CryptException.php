<?php

declare(strict_types=1);

namespace ConductorCore\Exception;

/**
 * A value could not be encrypted or decrypted: no key, the wrong key, a key of the wrong kind for the
 * envelope, or a malformed envelope. The message names what to fix and never carries key material.
 */
class CryptException extends RuntimeException
{
}
