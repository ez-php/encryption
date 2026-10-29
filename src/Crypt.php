<?php

declare(strict_types=1);

namespace EzPhp\Encryption;

use Closure;
use RuntimeException;

/**
 * Class Crypt
 *
 * Static facade for the configured Encrypter.
 * Call setEncrypter() before using any static methods, or setResolver() to
 * defer resolution until first use (done automatically by
 * EncryptionServiceProvider::boot(), so that a missing key only fails when
 * something actually encrypts). Throws RuntimeException if called before
 * either is set.
 *
 * Global state is intentional and documented — the ORM cast
 * (Orm\EncryptedString) is built through a static castFrom() and has no other
 * way to reach the encrypter.
 *
 * @package EzPhp\Encryption
 */
final class Crypt
{
    /**
     * @var Encrypter|null
     */
    private static ?Encrypter $encrypter = null;

    /**
     * @var (Closure(): Encrypter)|null
     */
    private static ?Closure $resolver = null;

    /**
     * Set the underlying encrypter directly.
     *
     * @param Encrypter $encrypter
     *
     * @return void
     */
    public static function setEncrypter(Encrypter $encrypter): void
    {
        self::$encrypter = $encrypter;
    }

    /**
     * Defer resolution of the underlying encrypter until the first facade call.
     *
     * @param Closure(): Encrypter $resolver
     *
     * @return void
     */
    public static function setResolver(Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * Reset the encrypter and resolver to null.
     *
     * Call this in setUp()/tearDown() of any test that touches the Crypt facade.
     *
     * @return void
     */
    public static function resetEncrypter(): void
    {
        self::$encrypter = null;
        self::$resolver = null;
    }

    /**
     * @param string $plaintext
     * @param string $context
     *
     * @return string
     */
    public static function encrypt(string $plaintext, string $context = ''): string
    {
        return self::encrypter()->encrypt($plaintext, $context);
    }

    /**
     * @param string $payload
     * @param string $context
     *
     * @throws DecryptException
     *
     * @return string
     */
    public static function decrypt(string $payload, string $context = ''): string
    {
        return self::encrypter()->decrypt($payload, $context);
    }

    /**
     * @return Encrypter
     */
    private static function encrypter(): Encrypter
    {
        if (self::$encrypter !== null) {
            return self::$encrypter;
        }

        if (self::$resolver !== null) {
            self::$encrypter = (self::$resolver)();

            return self::$encrypter;
        }

        throw new RuntimeException(
            'Encrypter not set. Register EncryptionServiceProvider or call Crypt::setEncrypter().'
        );
    }
}
