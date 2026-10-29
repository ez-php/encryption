<?php

declare(strict_types=1);

namespace Tests\Encryption;

use EzPhp\Testing\ApplicationTestCase as EzPhpApplicationTestCase;
use RuntimeException;

/**
 * Base class for encryption module tests that need a bootstrapped Application.
 *
 * Creates a temporary application root containing a config/encryption.php
 * file that reads from env vars at require-time. Because Config and all
 * service bindings are resolved lazily, env vars set in a test method before
 * the first make() call are picked up correctly.
 *
 * @package Tests\Encryption
 */
abstract class ApplicationTestCase extends EzPhpApplicationTestCase
{
    /**
     * @return string
     */
    protected function getBasePath(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ez-encryption-test-' . uniqid('', true);
        $configDir = $path . DIRECTORY_SEPARATOR . 'config';

        mkdir($configDir, 0o777, true);

        $content = <<<'PHP'
            <?php

            declare(strict_types=1);

            return [
                'key' => getenv('ENCRYPTION_KEY') ?: '',
                'previous_keys' => getenv('ENCRYPTION_PREVIOUS_KEYS') ?: '',
                'cookie_except' => ['XSRF-TOKEN', 'theme'],
            ];
            PHP;

        $result = file_put_contents($configDir . DIRECTORY_SEPARATOR . 'encryption.php', $content);

        if ($result === false) {
            throw new RuntimeException('Failed to write encryption.php for test at ' . $configDir);
        }

        return $path;
    }
}
