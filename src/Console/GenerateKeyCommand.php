<?php

declare(strict_types=1);

namespace EzPhp\Encryption\Console;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Output;
use EzPhp\Encryption\Key;

/**
 * Class GenerateKeyCommand
 *
 * `encryption:key` — prints a new random key in the `base64:...` format.
 * It never writes `.env` itself: where the key is stored (and which previous
 * keys stay valid) is an explicit deployment decision.
 *
 * @package EzPhp\Encryption\Console
 */
final readonly class GenerateKeyCommand implements CommandInterface
{
    /**
     * @return string
     */
    public function getName(): string
    {
        return 'encryption:key';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Generate a new encryption key';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return "Usage: ez encryption:key\n\n"
            . "Prints a new key. Set it as ENCRYPTION_KEY; to rotate, move the old key to\n"
            . 'ENCRYPTION_PREVIOUS_KEYS (comma-separated) so existing payloads still decrypt.';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        Output::line(Key::generate());

        return 0;
    }
}
