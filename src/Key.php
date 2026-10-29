<?php

declare(strict_types=1);

namespace EzPhp\Encryption;

/**
 * Class Key
 *
 * Generates and parses the configured key format: `base64:` followed by the
 * standard base64 encoding of 32 random bytes (the XChaCha20-Poly1305 key size).
 *
 * @package EzPhp\Encryption
 */
final class Key
{
    private const string PREFIX = 'base64:';

    /**
     * Generate a new random key in the configured format.
     *
     * @return string
     */
    public static function generate(): string
    {
        return self::PREFIX . base64_encode(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    }

    /**
     * Decode a configured `base64:...` key into its raw 32 bytes.
     *
     * @param string $configured
     *
     * @throws EncryptionException When the key is empty, lacks the prefix, is not valid base64 or has the wrong length.
     *
     * @return string
     */
    public static function parse(string $configured): string
    {
        if ($configured === '') {
            throw new EncryptionException('No encryption key configured. Generate one with `ez encryption:key` and set ENCRYPTION_KEY.');
        }

        if (!str_starts_with($configured, self::PREFIX)) {
            throw new EncryptionException('Encryption keys must start with "base64:". Generate one with `ez encryption:key`.');
        }

        $raw = base64_decode(substr($configured, strlen(self::PREFIX)), true);

        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new EncryptionException(sprintf(
                'Encryption keys must be base64 encoding of exactly %d bytes.',
                SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
            ));
        }

        return $raw;
    }
}
