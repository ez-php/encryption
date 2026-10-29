<?php

declare(strict_types=1);

namespace EzPhp\Encryption;

use EzPhp\Contracts\EzPhpException;

/**
 * Class EncryptionException
 *
 * Base exception of this module — thrown directly for configuration errors
 * (a missing, malformed or wrong-length key).
 *
 * @package EzPhp\Encryption
 */
class EncryptionException extends EzPhpException
{
}
