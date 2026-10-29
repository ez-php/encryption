<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Encryption\EncryptionException;
use EzPhp\Encryption\Key;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Class EncryptionKeyTest
 *
 * @package Tests
 */
#[CoversClass(Key::class)]
#[CoversClass(EncryptionException::class)]
final class EncryptionKeyTest extends TestCase
{
    public function test_generated_keys_parse_to_32_random_bytes(): void
    {
        $key = Key::generate();

        self::assertStringStartsWith('base64:', $key);
        self::assertSame(32, strlen(Key::parse($key)));
        self::assertNotSame($key, Key::generate());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidKeys(): array
    {
        return [
            'empty' => [''],
            'missing prefix' => [base64_encode(str_repeat('k', 32))],
            'invalid base64' => ['base64:***'],
            'wrong length' => ['base64:' . base64_encode(str_repeat('k', 16))],
        ];
    }

    #[DataProvider('invalidKeys')]
    public function test_invalid_keys_are_rejected(string $key): void
    {
        $this->expectException(EncryptionException::class);

        Key::parse($key);
    }
}
