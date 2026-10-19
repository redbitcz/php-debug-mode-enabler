<?php

/** @testCase */
declare(strict_types=1);

namespace Redbitcz\DebugMode;

// Capture outgoing cookies without sending HTTP headers from the CLI test process.
function setcookie(string $name, string $value, array $options): bool
{
    \Redbitcz\DebugModeTests\EnablerTest::$cookie = [$name, $value, $options];
    return true;
}

namespace Redbitcz\DebugModeTests;

use Nette\Utils\FileSystem;
use Redbitcz\DebugMode\Enabler;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/bootstrap.php';

class EnablerTest extends TestCase
{
    public static array $cookie = [];
    private string $tempDir;
    private array $originalCookies;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/debug-enabler-test-' . bin2hex(random_bytes(16));
        mkdir($this->tempDir, 0700);
        $this->originalCookies = $_COOKIE;
        $_COOKIE = [];
        self::$cookie = [];
    }

    protected function tearDown(): void
    {
        $_COOKIE = $this->originalCookies;
        FileSystem::delete($this->tempDir);
    }

    public function getInvalidStorage(): array
    {
        return [['null'], ['true'], ['42'], ['"invalid"']];
    }

    /** @dataProvider getInvalidStorage */
    public function testInvalidStorage(string $json): void
    {
        FileSystem::write($this->tempDir . '/debug/token.bin', $json);
        $_COOKIE['app-debug-token'] = 'unknown-token';
        $enabler = new Enabler($this->tempDir);
        Assert::null($enabler->isDebug());
        $enabler->activate(true);
        $_COOKIE[self::$cookie[0]] = self::$cookie[1];
        Assert::true((new Enabler($this->tempDir))->isDebug());
        $enabler->deactivate();
        Assert::null((new Enabler($this->tempDir))->isDebug());
    }

    public function testCookieSetterComposition(): void
    {
        $enabler = new Enabler($this->tempDir);
        $enabler->setSecure(false)->setCookieOptions(path: '/app')->activate(true);
        Assert::same('/app', self::$cookie[2]['path']);
        Assert::false(self::$cookie[2]['secure']);
        Assert::false(array_key_exists('samesite', self::$cookie[2]));

        $enabler->setCookieOptions(samesite: 'Lax')->activate(true);
        Assert::same('Lax', self::$cookie[2]['samesite']);
        $enabler->setCookieOptions(domain: 'example.test')->activate(true);
        Assert::same('Lax', self::$cookie[2]['samesite']);
        $enabler->setSecure()->activate(true);
        Assert::true(self::$cookie[2]['secure']);
        Assert::same('Strict', self::$cookie[2]['samesite']);
    }
}

(new EnablerTest())->run();
