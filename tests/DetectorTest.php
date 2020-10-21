<?php

declare(strict_types=1);

namespace Redbitcz\DebugModeTests;

use Redbitcz\DebugMode\Detector;
use Tester\Assert;
use Tester\Helpers;
use Tester\TestCase;

require __DIR__ . '/bootstrap.php';

/**
 * @testCase
 */
class DetectorTest extends TestCase
{
    private const DEBUG_ENV_NAME = 'APP_DEBUG';
    private const DEBUG_COOKIE_NAME = 'app-debug-mode';
    private const TEMP_DIR = __DIR__ . '/temp/enabler';

    protected function setUp(): void
    {
        @mkdir(self::TEMP_DIR, 0777, true);
        Helpers::purge(self::TEMP_DIR);
    }

    public function getEnvDataProvider(): array
    {
        return [
            [null, null],
            ['', null],
            ['0', false],
            ['1', true],
            ['2', null],
            ['-1', null],
            ['foo', null],
            ['bar', null],
        ];
    }

    /**
     * @dataProvider getEnvDataProvider
     * @param $testValue
     * @param $expected
     */
    public function testEnv($testValue, $expected): void
    {
        putenv(sprintf('%s%s%s', self::DEBUG_ENV_NAME, $testValue === null ? '' : '=', $testValue));

        $detector = new Detector(self::TEMP_DIR);
        Assert::equal($expected, $detector->isDebugModeByEnv());
    }

    public function getCookieDataProvider(): array
    {
        return [
            [null, null],
            ['', null],
            [0, false],
            ['0', false],
            ['1', null],
            ['1', null],
            ['2', null],
            ['-1', null],
            ['foo', null],
            ['bar', null],
        ];
    }

    /**
     * @dataProvider getCookieDataProvider
     * @param $testValue
     * @param $expected
     */
    public function testCookie($testValue, $expected): void
    {
        if ($testValue !== null) {
            $_COOKIE[self::DEBUG_COOKIE_NAME] = $testValue;
        }

        $detector = new Detector(self::TEMP_DIR);
        Assert::equal($expected, $detector->isDebugModeByCookie());
    }

    public function getIpDataProvider(): array
    {
        return [
            //  [null, null], // unable to test null, because then detector try load ip from `php_uname('n')`
            ['127.0.0.1', true],
            ['127.0.0.254', true],
            ['127.0.1.0', null],
            ['192.168.1.1', null],
            ['::1', true],
            ['2600:1005:b062:61e4:74d7:f292:802c:fbfd', null],
        ];
    }

    /**
     * @dataProvider getIpDataProvider
     * @param $testValue
     * @param $expected
     */
    public function testIp($testValue, $expected): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_FORWARDED']);
        $_SERVER['REMOTE_ADDR'] = $testValue;


        $detector = new Detector(self::TEMP_DIR);
        Assert::equal($expected, $detector->isDebugModeByIp());
    }

    public function getEnablerDataProvider(): array
    {
        return [
            //  [null], // unable to test null, because then detector fetch cookie from globals
            [true],
            [false],
        ];
    }

    /**
     * @dataProvider getEnablerDataProvider
     * @param $testValue
     */
    public function testEnabler($testValue): void
    {
        $detector = new Detector(self::TEMP_DIR);
        $detector->getEnabler()->override($testValue);
        Assert::equal($testValue, $detector->isDebugModeByEnabler());
    }
}

(new DetectorTest())->run();
