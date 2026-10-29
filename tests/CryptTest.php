<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Encryption\Crypt;
use EzPhp\Encryption\Encrypter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;

/**
 * Class CryptTest
 *
 * @package Tests
 */
#[CoversClass(Crypt::class)]
#[UsesClass(Encrypter::class)]
final class CryptTest extends TestCase
{
    protected function setUp(): void
    {
        Crypt::resetEncrypter();
    }

    protected function tearDown(): void
    {
        Crypt::resetEncrypter();
    }

    public function test_throws_when_no_encrypter_is_set(): void
    {
        $this->expectException(RuntimeException::class);

        Crypt::encrypt('value');
    }

    public function test_delegates_to_the_set_encrypter(): void
    {
        Crypt::setEncrypter(new Encrypter(str_repeat('k', 32)));

        self::assertSame('value', Crypt::decrypt(Crypt::encrypt('value', 'ctx'), 'ctx'));
    }

    public function test_resolver_runs_once_on_first_use(): void
    {
        $calls = 0;
        Crypt::setResolver(function () use (&$calls): Encrypter {
            $calls++;

            return new Encrypter(str_repeat('k', 32));
        });

        self::assertSame(0, $calls);
        Crypt::decrypt(Crypt::encrypt('value'));
        self::assertSame(1, $calls);
    }
}
