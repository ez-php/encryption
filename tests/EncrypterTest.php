<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Encryption\DecryptException;
use EzPhp\Encryption\Encrypter;
use EzPhp\Encryption\EncryptionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Class EncrypterTest
 *
 * @package Tests
 */
#[CoversClass(Encrypter::class)]
#[CoversClass(EncryptionException::class)]
#[CoversClass(DecryptException::class)]
final class EncrypterTest extends TestCase
{
    public function test_round_trips_plaintext(): void
    {
        $encrypter = new Encrypter(self::key('a'));

        self::assertSame('secret value', $encrypter->decrypt($encrypter->encrypt('secret value')));
        self::assertSame('', $encrypter->decrypt($encrypter->encrypt('')));
    }

    public function test_payload_is_versioned_url_safe_and_never_repeats(): void
    {
        $encrypter = new Encrypter(self::key('a'));
        $first = $encrypter->encrypt(str_repeat("\xff", 64));
        $second = $encrypter->encrypt(str_repeat("\xff", 64));

        self::assertStringStartsWith('ez1.', $first);
        self::assertMatchesRegularExpression('/^ez1\.[A-Za-z0-9_-]+$/', $first);
        self::assertNotSame($first, $second);
    }

    public function test_context_is_bound_to_the_payload(): void
    {
        $encrypter = new Encrypter(self::key('a'));
        $payload = $encrypter->encrypt('value', 'cookie:session');

        self::assertSame('value', $encrypter->decrypt($payload, 'cookie:session'));

        $this->expectException(DecryptException::class);
        $encrypter->decrypt($payload, 'cookie:remember');
    }

    public function test_tampered_ciphertext_is_rejected(): void
    {
        $encrypter = new Encrypter(self::key('a'));
        $payload = $encrypter->encrypt('value');
        $last = $payload[-1];
        $tampered = substr($payload, 0, -1) . ($last === 'A' ? 'B' : 'A');

        $this->expectException(DecryptException::class);
        $encrypter->decrypt($tampered);
    }

    public function test_previous_keys_decrypt_but_the_current_key_encrypts(): void
    {
        $old = new Encrypter(self::key('old'));
        $rotated = new Encrypter(self::key('new'), [self::key('old')]);
        $legacyPayload = $old->encrypt('legacy');

        self::assertSame('legacy', $rotated->decrypt($legacyPayload));
        self::assertSame('fresh', (new Encrypter(self::key('new')))->decrypt($rotated->encrypt('fresh')));
    }

    public function test_payload_from_an_unknown_key_is_rejected(): void
    {
        $payload = (new Encrypter(self::key('retired')))->encrypt('value');

        $this->expectException(DecryptException::class);
        (new Encrypter(self::key('new')))->decrypt($payload);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedPayloads(): array
    {
        return [
            'empty' => [''],
            'no version prefix' => ['abc'],
            'unknown version' => ['ez2.AAAA'],
            'not base64url' => ['ez1.!!!'],
            'too short' => ['ez1.AAAA'],
        ];
    }

    #[DataProvider('malformedPayloads')]
    public function test_malformed_payloads_are_rejected(string $payload): void
    {
        $this->expectException(DecryptException::class);

        (new Encrypter(self::key('a')))->decrypt($payload);
    }

    public function test_keys_must_have_32_bytes(): void
    {
        $this->expectException(EncryptionException::class);

        new Encrypter(self::key('a'), ['too short']);
    }

    /**
     * A deterministic 32-byte key derived from $seed.
     *
     * @param string $seed
     *
     * @return string
     */
    private static function key(string $seed): string
    {
        return hash('sha256', $seed, true);
    }
}
