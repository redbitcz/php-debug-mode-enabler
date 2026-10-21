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

    private function acceptCookie(): string
    {
        Assert::same('app-debug-token', self::$cookie[0]);
        $_COOKIE[self::$cookie[0]] = self::$cookie[1];
        return self::$cookie[1];
    }

    public function testPersistenceAndRevocation(): void
    {
        $enabler = new Enabler($this->tempDir);
        Assert::null($enabler->isDebug());
        $enabler->activate(true);
        $firstToken = $this->acceptCookie();
        Assert::same(0, self::$cookie[2]['expires']);
        Assert::true(self::$cookie[2]['httponly']);
        Assert::true((new Enabler($this->tempDir))->isDebug());
        Assert::notContains($firstToken, FileSystem::read($this->tempDir . '/debug/token.bin'));

        // A subsequent request replaces and revokes the previous browser token.
        $expires = time() + 3600;
        (new Enabler($this->tempDir))->activate(false, $expires);
        $secondToken = $this->acceptCookie();
        Assert::same($expires, self::$cookie[2]['expires']);
        Assert::false((new Enabler($this->tempDir))->isDebug());
        $_COOKIE['app-debug-token'] = $firstToken;
        Assert::null((new Enabler($this->tempDir))->isDebug());
        $_COOKIE['app-debug-token'] = $secondToken;
        (new Enabler($this->tempDir))->deactivate();
        Assert::same('', self::$cookie[1]);
        Assert::null((new Enabler($this->tempDir))->isDebug());
        Assert::false(is_file($this->tempDir . '/debug/token.bin'));
    }

    public function testSameRequestModeAndCookieLifecycle(): void
    {
        $enabler = new Enabler($this->tempDir);
        $enabler->activate(true);
        Assert::true($enabler->isDebug());
        $enabler->activate(false);
        Assert::false($enabler->isDebug());
        $enabler->deactivate();
        Assert::null($enabler->isDebug());
        Assert::same('', self::$cookie[1]);
        Assert::true(self::$cookie[2]['expires'] <= time());
        // Revoking tokens issued earlier in this same request remains a separate lifecycle issue.
    }

    public function testIndependentBrowserTokens(): void
    {
        (new Enabler($this->tempDir))->activate(true);
        $firstToken = self::$cookie[1];
        (new Enabler($this->tempDir))->activate(false);
        $secondToken = $this->acceptCookie();
        Assert::false((new Enabler($this->tempDir))->isDebug());
        $_COOKIE['app-debug-token'] = $firstToken;
        Assert::true((new Enabler($this->tempDir))->isDebug());
        (new Enabler($this->tempDir))->deactivate();
        $_COOKIE['app-debug-token'] = $secondToken;
        Assert::false((new Enabler($this->tempDir))->isDebug());
    }

    public function testOverrideDoesNotPersist(): void
    {
        $enabler = new Enabler($this->tempDir);
        $enabler->override(true);
        Assert::true($enabler->isDebug());
        $enabler->override(false);
        Assert::false($enabler->isDebug());
        Assert::null((new Enabler($this->tempDir))->isDebug());
        Assert::same([], self::$cookie);
        Assert::false(is_dir($this->tempDir . '/debug'));
    }

    public function testExpiredAndForgedTokens(): void
    {
        $enabler = new Enabler($this->tempDir);
        $enabler->activate(true, time() + 3600);
        $token = $this->acceptCookie();
        $_COOKIE['app-debug-token'] = substr($token, 0, 15) . str_repeat('!', 15);
        Assert::null((new Enabler($this->tempDir))->isDebug());
        $_COOKIE['app-debug-token'] = $token;
        $file = $this->tempDir . '/debug/token.bin';
        $list = json_decode(FileSystem::read($file), true, flags: JSON_THROW_ON_ERROR);
        $list[0]['expire'] = time() - 1;
        FileSystem::write($file, json_encode($list, JSON_THROW_ON_ERROR));
        Assert::null((new Enabler($this->tempDir))->isDebug());
        (new Enabler($this->tempDir))->activate(false);
        $this->acceptCookie();
        Assert::false((new Enabler($this->tempDir))->isDebug());
        Assert::count(1, json_decode(FileSystem::read($file), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testMalformedJsonWarnsAndRecovers(): void
    {
        $file = $this->tempDir . '/debug/token.bin';
        FileSystem::write($file, '{invalid');
        $_COOKIE['app-debug-token'] = 'unknown-token';
        Assert::error(function (): void {
            Assert::null((new Enabler($this->tempDir))->isDebug());
        }, E_USER_WARNING, '%a%JSON Exception%a%');
        $_COOKIE = [];
        Assert::error(function (): void {
            (new Enabler($this->tempDir))->activate(true);
        }, E_USER_WARNING, '%a%JSON Exception%a%');
        $this->acceptCookie();
        Assert::true((new Enabler($this->tempDir))->isDebug());
    }

    public function testSeparateStorageCannotResolveToken(): void
    {
        (new Enabler($this->tempDir))->activate(true);
        $this->acceptCookie();
        Assert::null((new Enabler($this->tempDir . '/other'))->isDebug());
        Assert::true((new Enabler($this->tempDir))->isDebug());
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
