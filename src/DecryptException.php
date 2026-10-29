<?php

declare(strict_types=1);

namespace EzPhp\Encryption;

/**
 * Class DecryptException
 *
 * Thrown when a payload cannot be decrypted: malformed, unknown version, no
 * configured key matches, or authentication failed (tampered ciphertext, wrong
 * context). The message deliberately does not say which.
 *
 * @package EzPhp\Encryption
 */
final class DecryptException extends EncryptionException
{
}
