<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Application\Application;
use EzPhp\Encryption\Console\GenerateKeyCommand;
use EzPhp\Encryption\Crypt;
use EzPhp\Encryption\Encrypter;
use EzPhp\Encryption\EncryptionException;
use EzPhp\Encryption\EncryptionServiceProvider;
use EzPhp\Encryption\Key;
use EzPhp\Encryption\Middleware\EncryptedCookieMiddleware;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Encryption\ApplicationTestCase;

/**
 * Class EncryptionServiceProviderTest
 *
 * @package Tests
 */
#[CoversClass(EncryptionServiceProvider::class)]
#[UsesClass(Crypt::class)]
#[UsesClass(Encrypter::class)]
#[UsesClass(Key::class)]
#[UsesClass(EncryptedCookieMiddleware::class)]
#[UsesClass(GenerateKeyCommand::class)]
final class EncryptionServiceProviderTest extends ApplicationTestCase
{
    private const array ENV_VARS = ['ENCRYPTION_KEY', 'ENCRYPTION_PREVIOUS_KEYS'];

    protected function setUp(): void
    {
        $this->clearEnv();
        Crypt::resetEncrypter();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->clearEnv();
        Crypt::resetEncrypter();

        parent::tearDown();
    }

    /**
     * @param Application $app
     *
     * @return void
     */
    protected function configureApplication(Application $app): void
    {
        $app->register(EncryptionServiceProvider::class);
    }

    public function test_encrypter_uses_the_configured_key_and_previous_keys(): void
    {
        $old = Key::generate();
        $legacy = (new Encrypter(Key::parse($old)))->encrypt('legacy');
        putenv('ENCRYPTION_KEY=' . Key::generate());
        putenv('ENCRYPTION_PREVIOUS_KEYS= ' . $old . ' ,');

        $encrypter = $this->app()->make(Encrypter::class);

        self::assertSame('legacy', $encrypter->decrypt($legacy));
    }

    public function test_crypt_facade_is_wired_lazily(): void
    {
        putenv('ENCRYPTION_KEY=' . Key::generate());

        self::assertSame('value', Crypt::decrypt(Crypt::encrypt('value')));
    }

    public function test_missing_key_fails_on_first_use(): void
    {
        $this->expectException(EncryptionException::class);

        $this->app()->make(Encrypter::class);
    }

    public function test_cookie_middleware_reads_the_except_list_from_config(): void
    {
        putenv('ENCRYPTION_KEY=' . Key::generate());

        $middleware = $this->app()->make(EncryptedCookieMiddleware::class);
        $response = $middleware->handle(
            new Request('GET', '/'),
            fn (): ResponseInterface => (new Response())->withCookie('theme', 'dark')->withCookie('session', 'abc'),
        );

        self::assertSame('dark', $response->cookies()[0]->value());
        self::assertNotSame('abc', $response->cookies()[1]->value());
    }

    public function test_registers_the_key_command(): void
    {
        self::assertContains(GenerateKeyCommand::class, $this->app()->getCommands());
    }

    /**
     * @return void
     */
    private function clearEnv(): void
    {
        foreach (self::ENV_VARS as $var) {
            putenv($var);
        }
    }
}
