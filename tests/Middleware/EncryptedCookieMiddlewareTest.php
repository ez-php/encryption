<?php

declare(strict_types=1);

namespace Tests\Middleware;

use EzPhp\Encryption\Encrypter;
use EzPhp\Encryption\Middleware\EncryptedCookieMiddleware;
use EzPhp\Http\Cookie;
use EzPhp\Http\Request;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class EncryptedCookieMiddlewareTest
 *
 * @package Tests\Middleware
 */
#[CoversClass(EncryptedCookieMiddleware::class)]
#[UsesClass(Encrypter::class)]
final class EncryptedCookieMiddlewareTest extends TestCase
{
    private Encrypter $encrypter;

    private EncryptedCookieMiddleware $middleware;

    protected function setUp(): void
    {
        $this->encrypter = new Encrypter(str_repeat('k', 32));
        $this->middleware = new EncryptedCookieMiddleware($this->encrypter);
    }

    public function test_incoming_cookies_reach_the_handler_decrypted(): void
    {
        $request = self::request([
            'session' => $this->encrypter->encrypt('abc', 'cookie:session'),
            'XSRF-TOKEN' => 'plain-token',
        ]);
        $seen = null;

        $this->middleware->handle($request, function (RequestInterface $request) use (&$seen): ResponseInterface {
            $seen = $request;

            return new Response();
        });

        self::assertInstanceOf(RequestInterface::class, $seen);
        self::assertSame('abc', $seen->cookie('session'));
        self::assertSame('plain-token', $seen->cookie('XSRF-TOKEN'));
    }

    public function test_undecryptable_and_swapped_cookies_are_dropped(): void
    {
        $request = self::request([
            'tampered' => 'plaintext-from-the-client',
            'remember' => $this->encrypter->encrypt('abc', 'cookie:session'),
            'list' => ['not', 'a', 'string'],
        ]);
        $seen = null;

        $this->middleware->handle($request, function (RequestInterface $request) use (&$seen): ResponseInterface {
            $seen = $request;

            return new Response();
        });

        self::assertInstanceOf(Request::class, $seen);
        self::assertSame([], $seen->cookies());
    }

    public function test_outgoing_cookies_are_encrypted_with_their_attributes_kept(): void
    {
        $response = $this->middleware->handle(self::request([]), fn (): ResponseInterface => (new Response())
            ->withCookie('session', 'abc', 3600, '/app', 'example.com', true, true, 'Lax')
            ->withCookie('XSRF-TOKEN', 'plain-token')
            ->withCookie('gone', '', -1));

        [$session, $xsrf, $gone] = $response->cookies();

        self::assertNotSame('abc', $session->value());
        self::assertSame('abc', $this->encrypter->decrypt($session->value(), 'cookie:session'));
        self::assertSame(
            [3600, '/app', 'example.com', true, true, 'Lax'],
            [$session->ttl(), $session->path(), $session->domain(), $session->isSecure(), $session->isHttpOnly(), $session->sameSite()],
        );
        self::assertSame('plain-token', $xsrf->value());
        self::assertSame('', $gone->value());
    }

    public function test_round_trip_through_the_middleware(): void
    {
        $response = $this->middleware->handle(
            self::request([]),
            fn (): ResponseInterface => (new Response())->withCookie('cart', '{"items":3}'),
        );
        $cookie = $response->cookies()[0];
        self::assertInstanceOf(Cookie::class, $cookie);
        $seen = null;

        $this->middleware->handle(self::request(['cart' => $cookie->value()]), function (RequestInterface $request) use (&$seen): ResponseInterface {
            $seen = $request->cookie('cart');

            return new Response();
        });

        self::assertSame('{"items":3}', $seen);
    }

    public function test_custom_except_list_replaces_the_default(): void
    {
        $middleware = new EncryptedCookieMiddleware($this->encrypter, ['theme']);

        $response = $middleware->handle(self::request([]), fn (): ResponseInterface => (new Response())
            ->withCookie('theme', 'dark')
            ->withCookie('XSRF-TOKEN', 'token'));

        self::assertSame('dark', $response->cookies()[0]->value());
        self::assertNotSame('token', $response->cookies()[1]->value());
    }

    /**
     * @param array<string, mixed> $cookies
     *
     * @return Request
     */
    private static function request(array $cookies): Request
    {
        return new Request('GET', '/', cookies: $cookies);
    }
}
