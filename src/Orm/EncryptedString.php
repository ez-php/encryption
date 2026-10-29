<?php

declare(strict_types=1);

namespace EzPhp\Encryption\Orm;

use EzPhp\Encryption\Crypt;
use EzPhp\Encryption\DecryptException;
use EzPhp\Orm\CastableInterface;

/**
 * Class EncryptedString
 *
 * ORM cast for a string column stored encrypted at rest:
 *
 *     protected static array $casts = ['iban' => EncryptedString::class];
 *     $entity->iban = EncryptedString::of('DE89...');
 *     $entity->iban->value(); // plaintext
 *
 * Goes through the Crypt facade (EncryptionServiceProvider wires it), because
 * the ORM builds casts through the static castFrom() and cannot inject.
 *
 * Every encryption uses a fresh nonce, so the ciphertext is memoised: a value
 * read from storage writes back the exact ciphertext it was loaded from, and a
 * new value encrypts once, so DirtyTracker and Hydrator (both call castTo())
 * see the same storage value and an unchanged entity is never dirty.
 *
 * Encrypted with an empty context — any `Crypt::decrypt($column)` reads it.
 * Deliberately not Stringable: plaintext only leaves through an explicit value().
 *
 * @package EzPhp\Encryption\Orm
 */
final class EncryptedString implements CastableInterface
{
    /**
     * @param string      $plaintext
     * @param string|null $ciphertext Storage value; null until first castTo() for new values.
     */
    private function __construct(
        private readonly string $plaintext,
        private ?string $ciphertext,
    ) {
    }

    /**
     * Wrap a plaintext value for assignment to an encrypted column.
     *
     * @param string $plaintext
     *
     * @return self
     */
    public static function of(string $plaintext): self
    {
        return new self($plaintext, null);
    }

    /**
     * Decrypt a raw storage value.
     *
     * @param mixed $value
     *
     * @throws DecryptException When the value is not a string or does not decrypt.
     *
     * @return static
     */
    public static function castFrom(mixed $value): static
    {
        if (!is_string($value)) {
            throw new DecryptException('An encrypted column must hold a string payload.');
        }

        return new static(Crypt::decrypt($value), $value);
    }

    /**
     * Return the ciphertext to store, encrypting once on first call.
     *
     * @return string
     */
    public function castTo(): string
    {
        return $this->ciphertext ??= Crypt::encrypt($this->plaintext);
    }

    /**
     * @return string
     */
    public function value(): string
    {
        return $this->plaintext;
    }
}
