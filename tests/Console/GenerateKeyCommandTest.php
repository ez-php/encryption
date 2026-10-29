<?php

declare(strict_types=1);

namespace Tests\Console;

use EzPhp\Encryption\Console\GenerateKeyCommand;
use EzPhp\Encryption\Key;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class GenerateKeyCommandTest
 *
 * @package Tests\Console
 */
#[CoversClass(GenerateKeyCommand::class)]
#[UsesClass(Key::class)]
final class GenerateKeyCommandTest extends TestCase
{
    public function test_prints_a_parseable_key(): void
    {
        $command = new GenerateKeyCommand();

        ob_start();
        $exitCode = $command->handle([]);
        $output = trim((string) ob_get_clean());

        self::assertSame(0, $exitCode);
        self::assertSame('encryption:key', $command->getName());
        self::assertNotSame('', $command->getDescription());
        self::assertStringContainsString('ENCRYPTION_KEY', $command->getHelp());
        self::assertSame(32, strlen(Key::parse($output)));
    }
}
