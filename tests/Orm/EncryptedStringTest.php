<?php

declare(strict_types=1);

namespace Tests\Orm;

use EzPhp\Encryption\Crypt;
use EzPhp\Encryption\DecryptException;
use EzPhp\Encryption\Encrypter;
use EzPhp\Encryption\Orm\EncryptedString;
use EzPhp\Orm\DirtyTracker;
use EzPhp\Orm\Entity;
use EzPhp\Orm\Hydrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Entity with an encrypted column.
 */
final class EncryptedStringTestAccount extends Entity
{
    protected static array $casts = ['iban' => EncryptedString::class];
}

/**
 * Class EncryptedStringTest
 *
 * @package Tests\Orm
 */
#[CoversClass(EncryptedString::class)]
#[UsesClass(Crypt::class)]
#[UsesClass(Encrypter::class)]
final class EncryptedStringTest extends TestCase
{
    protected function setUp(): void
    {
        Crypt::setEncrypter(new Encrypter(str_repeat('k', 32)));
    }

    protected function tearDown(): void
    {
        Crypt::resetEncrypter();
    }

    public function test_entity_reads_the_decrypted_value(): void
    {
        $account = (new Hydrator())->hydrate(EncryptedStringTestAccount::class, ['iban' => Crypt::encrypt('DE89 3704')]);

        $iban = $account->getAttribute('iban');
        self::assertInstanceOf(EncryptedString::class, $iban);
        self::assertSame('DE89 3704', $iban->value());
    }

    public function test_new_values_are_extracted_as_ciphertext_once(): void
    {
        $account = new EncryptedStringTestAccount();
        $account->setAttribute('iban', EncryptedString::of('DE89 3704'));

        $stored = (new Hydrator())->extract($account)['iban'];

        self::assertIsString($stored);
        self::assertSame('DE89 3704', Crypt::decrypt($stored));
        self::assertSame($stored, (new Hydrator())->extract($account)['iban']);
    }

    public function test_reassigning_a_loaded_value_keeps_the_entity_clean(): void
    {
        $tracker = new DirtyTracker();
        $account = (new Hydrator())->hydrate(EncryptedStringTestAccount::class, ['iban' => Crypt::encrypt('DE89 3704')]);
        $tracker->track($account);

        $account->setAttribute('iban', $account->getAttribute('iban'));

        self::assertSame([], $tracker->dirty($account));
    }

    public function test_a_changed_value_makes_the_entity_dirty(): void
    {
        $tracker = new DirtyTracker();
        $account = (new Hydrator())->hydrate(EncryptedStringTestAccount::class, ['iban' => Crypt::encrypt('DE89 3704')]);
        $tracker->track($account);

        $account->setAttribute('iban', EncryptedString::of('AT61 1904'));

        self::assertArrayHasKey('iban', $tracker->dirty($account));
    }

    public function test_non_string_storage_values_are_rejected(): void
    {
        $this->expectException(DecryptException::class);

        EncryptedString::castFrom(42);
    }
}
