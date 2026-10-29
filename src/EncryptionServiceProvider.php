<?php

declare(strict_types=1);

namespace EzPhp\Encryption;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\ServiceProvider;
use EzPhp\Encryption\Console\GenerateKeyCommand;
use EzPhp\Encryption\Middleware\EncryptedCookieMiddleware;

/**
 * Class EncryptionServiceProvider
 *
 * Binds Encrypter (from the configured current and previous keys) and
 * EncryptedCookieMiddleware, wires the Crypt facade in boot() and registers
 * the `encryption:key` command.
 *
 * Bindings resolve lazily: a missing key only throws when something actually
 * encrypts or decrypts, so `ez encryption:key` works before a key exists.
 *
 * Configuration keys (in config/encryption.php or environment):
 *   - encryption.key            — current key, `base64:...` (ENCRYPTION_KEY)
 *   - encryption.previous_keys  — decrypt-only keys: list, or comma-separated string (ENCRYPTION_PREVIOUS_KEYS)
 *   - encryption.cookie_except  — cookie names EncryptedCookieMiddleware leaves alone (default: XSRF-TOKEN)
 *
 * @package EzPhp\Encryption
 */
final class EncryptionServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(Encrypter::class, function (ContainerInterface $app): Encrypter {
            $config = $app->make(ConfigInterface::class);
            $key = $config->get('encryption.key', '');

            return new Encrypter(
                Key::parse(is_string($key) ? $key : ''),
                array_map(Key::parse(...), $this->stringList($config->get('encryption.previous_keys', []))),
            );
        });

        $this->app->bind(
            EncryptedCookieMiddleware::class,
            function (ContainerInterface $app): EncryptedCookieMiddleware {
                $except = $app->make(ConfigInterface::class)->get('encryption.cookie_except', ['XSRF-TOKEN']);

                return new EncryptedCookieMiddleware($app->make(Encrypter::class), $this->stringList($except));
            }
        );
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        Crypt::setResolver(fn (): Encrypter => $this->app->make(Encrypter::class));

        // ez-php/console is a suggest; registered by class name, so nothing is
        // autoloaded unless the application's console runs it.
        $this->registerCommand(GenerateKeyCommand::class);
    }

    /**
     * Normalise a list config value — an array, or a comma-separated string —
     * into its non-empty trimmed strings.
     *
     * @param mixed $value
     *
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        $items = is_string($value) ? explode(',', $value) : (is_array($value) ? $value : []);
        $list = [];

        foreach ($items as $item) {
            if (is_string($item) && trim($item) !== '') {
                $list[] = trim($item);
            }
        }

        return $list;
    }
}
