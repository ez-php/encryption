<?php

declare(strict_types=1);

namespace EzPhp\Encryption\Middleware;

use EzPhp\Contracts\MiddlewareInterface;
use EzPhp\Encryption\DecryptException;
use EzPhp\Encryption\Encrypter;
use EzPhp\Http\Cookie;
use EzPhp\Http\Request;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\ResponseInterface;

/**
 * Class EncryptedCookieMiddleware
 *
 * Decrypts incoming cookie values before the handler runs and encrypts the
 * cookies the handler queued on the way out, so application code only ever
 * sees plaintext.
 *
 * Each value is encrypted with the context `cookie:<name>`, so a value cannot
 * be replayed under another cookie name. An incoming cookie that does not
 * decrypt (tampered, foreign, made with a retired key) is dropped — the handler
 * sees it as absent rather than as attacker-controlled plaintext. Cookies in
 * $except (default: `XSRF-TOKEN`, which client-side JavaScript must read) pass
 * through untouched in both directions, as do deletion cookies (negative TTL).
 *
 * Only the concrete `EzPhp\Http\Request` can carry rewritten cookies
 * (`Request::withCookies()`); any other RequestInterface is passed on as-is.
 *
 * @package EzPhp\Encryption\Middleware
 */
final readonly class EncryptedCookieMiddleware implements MiddlewareInterface
{
    /**
     * @param Encrypter    $encrypter
     * @param list<string> $except    Cookie names never encrypted or decrypted.
     */
    public function __construct(
        private Encrypter $encrypter,
        private array $except = ['XSRF-TOKEN'],
    ) {
    }

    /**
     * @param RequestInterface $request
     * @param callable         $next
     *
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        if ($request instanceof Request) {
            $request = $request->withCookies($this->decryptCookies($request->cookies()));
        }

        /** @var ResponseInterface $response */
        $response = $next($request);

        return $response->withCookies(array_map($this->encryptCookie(...), $response->cookies()));
    }

    /**
     * @param array<string, mixed> $cookies
     *
     * @return array<string, mixed>
     */
    private function decryptCookies(array $cookies): array
    {
        $decrypted = [];

        foreach ($cookies as $name => $value) {
            if (in_array($name, $this->except, true)) {
                $decrypted[$name] = $value;

                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            try {
                $decrypted[$name] = $this->encrypter->decrypt($value, self::context($name));
            } catch (DecryptException) {
                // Dropped: an undecryptable value is never handed on as plaintext.
            }
        }

        return $decrypted;
    }

    /**
     * @param Cookie $cookie
     *
     * @return Cookie
     */
    private function encryptCookie(Cookie $cookie): Cookie
    {
        if ($cookie->ttl() < 0 || in_array($cookie->name(), $this->except, true)) {
            return $cookie;
        }

        return new Cookie(
            $cookie->name(),
            $this->encrypter->encrypt($cookie->value(), self::context($cookie->name())),
            $cookie->ttl(),
            $cookie->path(),
            $cookie->domain(),
            $cookie->isSecure(),
            $cookie->isHttpOnly(),
            $cookie->sameSite(),
        );
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private static function context(string $name): string
    {
        return 'cookie:' . $name;
    }
}
