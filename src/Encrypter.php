<?php

declare(strict_types=1);

namespace EzPhp\Encryption;

/**
 * Class Encrypter
 *
 * Symmetric authenticated encryption of strings with ext-sodium's
 * XChaCha20-Poly1305 (IETF) AEAD.
 *
 * Payload format: `ez1.` + base64url(key id (4 bytes) . nonce (24 bytes) . ciphertext+tag).
 * The key id is the first 4 bytes of a BLAKE2b hash of the key, so decrypt()
 * picks the matching key directly. The version prefix, key id and the caller's
 * $context are bound as additional data: a payload encrypted for one context
 * (e.g. cookie "session") does not decrypt under another.
 *
 * Key rotation: encrypt() always uses the current key; decrypt() also accepts
 * payloads made with any of the previous keys.
 *
 * @package EzPhp\Encryption
 */
final readonly class Encrypter
{
    private const string VERSION = 'ez1';

    private const int KEY_ID_BYTES = 4;

    /**
     * Raw keys keyed by position; index 0 is the current key.
     *
     * @var list<string>
     */
    private array $keys;

    /**
     * @param string       $key          Current raw 32-byte key (see Key::parse()).
     * @param list<string> $previousKeys Raw 32-byte keys accepted for decryption only.
     *
     * @throws EncryptionException When a key does not have exactly 32 bytes.
     */
    public function __construct(string $key, array $previousKeys = [])
    {
        $keys = [$key, ...$previousKeys];

        foreach ($keys as $candidate) {
            if (strlen($candidate) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
                throw new EncryptionException(sprintf(
                    'Encryption keys must be exactly %d raw bytes; decode configured keys with Key::parse().',
                    SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
                ));
            }
        }

        $this->keys = $keys;
    }

    /**
     * Encrypt $plaintext with the current key.
     *
     * @param string $plaintext
     * @param string $context   Optional binding, e.g. a cookie name; decrypt() must pass the same value.
     *
     * @return string URL- and cookie-safe payload.
     */
    public function encrypt(string $plaintext, string $context = ''): string
    {
        $keyId = self::keyId($this->keys[0]);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            self::additionalData($keyId, $context),
            $nonce,
            $this->keys[0],
        );

        return self::VERSION . '.' . sodium_bin2base64($keyId . $nonce . $ciphertext, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /**
     * Decrypt a payload produced by encrypt() under the current or a previous key.
     *
     * @param string $payload
     * @param string $context The value passed to encrypt().
     *
     * @throws DecryptException When the payload is malformed, no key matches or authentication fails.
     *
     * @return string
     */
    public function decrypt(string $payload, string $context = ''): string
    {
        $prefix = self::VERSION . '.';

        if (!str_starts_with($payload, $prefix)) {
            throw new DecryptException('The payload is invalid.');
        }

        try {
            $raw = sodium_base642bin(substr($payload, strlen($prefix)), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (\SodiumException) {
            throw new DecryptException('The payload is invalid.');
        }

        $headerBytes = self::KEY_ID_BYTES + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

        if (strlen($raw) < $headerBytes + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
            throw new DecryptException('The payload is invalid.');
        }

        $keyId = substr($raw, 0, self::KEY_ID_BYTES);
        $nonce = substr($raw, self::KEY_ID_BYTES, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = substr($raw, $headerBytes);

        foreach ($this->keys as $key) {
            if (!hash_equals(self::keyId($key), $keyId)) {
                continue;
            }

            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $ciphertext,
                self::additionalData($keyId, $context),
                $nonce,
                $key,
            );

            if ($plaintext !== false) {
                return $plaintext;
            }
        }

        throw new DecryptException('The payload could not be decrypted.');
    }

    /**
     * @param string $key
     *
     * @return string
     */
    private static function keyId(string $key): string
    {
        return substr(sodium_crypto_generichash($key, '', 16), 0, self::KEY_ID_BYTES);
    }

    /**
     * @param string $keyId
     * @param string $context
     *
     * @return string
     */
    private static function additionalData(string $keyId, string $context): string
    {
        return self::VERSION . $keyId . $context;
    }
}
