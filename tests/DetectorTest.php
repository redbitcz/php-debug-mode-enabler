<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2022 Redbit s.r.o., Jakub Bouček
 * @testCase
 */

declare(strict_types=1);

namespace Redbitcz\DebugModeTests;

use Redbitcz\DebugMode\Detector;
use Redbitcz\DebugMode\Enabler;
use Redbitcz\DebugMode\InconsistentEnablerModeException;
use Redbitcz\DebugMode\Plugin\Plugin;
use Tester\Assert;
use Tester\Helpers;
use Tester\TestCase;

require __DIR__ . '/bootstrap.php';

class DetectorTest extends TestCase
{
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
        putenv(sprintf('%s%s%s', Detector::DEBUG_ENV_NAME, $testValue === null ? '' : '=', $testValue));

        $detector = new Detector();
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
            $_COOKIE[Detector::DEBUG_COOKIE_NAME] = $testValue;
        }

        $detector = new Detector();
        Assert::equal($expected, $detector->isDebugModeByCookie());
    }

    public function getIpDataProvider(): array
    {
        return [
            //  [null, null], // unable to test null, because then detector try load ip from `php_uname('n')`
            ['127.0.0.1', true],
            ['127.0.0.254', null],
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
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_FORWARDED'], $_SERVER['HTTP_X_REAL_IP']);
        $_SERVER['REMOTE_ADDR'] = $testValue;


        $detector = new Detector();
        Assert::equal($expected, $detector->isDebugModeByIp());
    }

    public function getSettedIpDataProvider(): array
    {
        return [
            [['127.0.0.1'], '127.0.0.1', true],
            [['127.0.0.2'], '127.0.0.1', null],
            [['127.0.0.1'], '127.0.0.254', null],
            [['127.0.0.1', '127.0.1.0'], '127.0.1.0', true],
            [['127.0.0.1'], '127.0.1.0', null],
            [['127.0.0.1'], '192.168.1.1', null],
            [['127.0.0.1'], '::1', null],
            [['127.0.0.1', '2600:1005:b062:61e4:74d7:f292:802c:fbfd'], '2600:1005:b062:61e4:74d7:f292:802c:fbfd', true],
            [['127.0.0.1'], '2600:1005:b062:61e4:74d7:f292:802c:fbfd', null],
        ];
    }

    /**
     * @dataProvider getSettedIpDataProvider
     */
    public function testSettedIp(array $setIp, $testValue, $expected): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_FORWARDED']);
        $_SERVER['REMOTE_ADDR'] = $testValue;


        $detector = new Detector();
        $detector->setAllowedIp(...$setIp);
        Assert::equal($expected, $detector->isDebugModeByIp());
    }

    public function getAddedIpDataProvider(): array
    {
        return [
            [['10.0.0.1'], '127.0.0.1', true],
            [['10.0.0.1'], '127.0.0.254', null],
            [['10.0.0.1'], '127.0.1.0', null],
            [['10.0.0.1'], '192.168.1.1', null],
            [['10.0.0.1'], '::1', true],
            [['10.0.0.1'], '2600:1005:b062:61e4:74d7:f292:802c:fbfd', null],
            [['10.0.0.1'], '10.0.0.1', true],
            [['10.0.0.1', '10.0.0.2'], '10.0.0.2', true],
        ];
    }

    /**
     * @dataProvider getAddedIpDataProvider
     */
    public function testAddedIp(array $setIp, $testValue, $expected): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_FORWARDED']);
        $_SERVER['REMOTE_ADDR'] = $testValue;


        $detector = new Detector();
        $detector->addAllowedIp(...$setIp);
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
        $detector = new Detector(Detector::MODE_FULL, new Enabler(self::TEMP_DIR));
        $detector->getEnabler()->override($testValue);
        Assert::equal($testValue, $detector->isDebugModeByEnabler());
    }

    public function testMissingEnablerMode(): void
    {
        Assert::exception(function () {
            new Detector(Detector::MODE_FULL);
        }, InconsistentEnablerModeException::class);
    }

    public function testMissingEnabler(): void
    {
        Assert::exception(function () {
            $detector = new Detector(Detector::MODE_SIMPLE);
            $detector->getEnabler();
        }, InconsistentEnablerModeException::class);
    }

    public function testMissingEnablerShortcut(): void
    {
        Assert::exception(function () {
            Detector::detect(Detector::MODE_FULL);
        }, InconsistentEnablerModeException::class);
    }

    public function testPluginPrepend(): void
    {
        $detector = new Detector(0);
        $detector->prependPlugin(
            new class implements Plugin {
                public function __invoke(Detector $detector): ?bool
                {
                    Assert::true(true);
                    return null;
                }
            }
        );
        $detector->isDebugMode();
    }

    public function testPluginAppend(): void
    {
        $detector = new Detector(0);
        $detector->appendPlugin(
            new class implements Plugin {
                public function __invoke(Detector $detector): ?bool
                {
                    Assert::true(true);
                    return null;
                }
            }
        );
        $detector->isDebugMode();
    }

    public function getPluginPrependReturnsProvider(): array
    {
        return [
            [null, true],
            [true, true],
            [false, false],
        ];
    }

    /**
     * @dataProvider getPluginPrependReturnsProvider
     */
    public function testPluginPrependReturn(?bool $state, ?bool $result): void
    {
        $detector = new Detector(0);
        $detector->prependPlugin(
            new class() implements Plugin {
                public function __invoke(Detector $detector): ?bool
                {
                    return true;
                }
            }
        );
        $detector->prependPlugin(
            new class($state) implements Plugin {
                private ?bool $state;

                public function __construct(?bool $state)
                {
                    $this->state = $state;
                }

                public function __invoke(Detector $detector): ?bool
                {
                    return $this->state;
                }
            }
        );

        Assert::equal($result, $detector->isDebugMode());
    }


    public function getPluginAppendReturnsProvider(): array
    {
        return [
            [null, false],
            [true, false],
            [false, false],
        ];
    }

    /**
     * @dataProvider getPluginAppendReturnsProvider
     */
    public function testPluginAppendReturn(?bool $state, ?bool $result): void
    {
        $detector = new Detector(0);
        $detector->appendPlugin(
            new class() implements Plugin {
                public function __invoke(Detector $detector): ?bool
                {
                    return false;
                }
            }
        );
        $detector->appendPlugin(
            new class($state) implements Plugin {
                private ?bool $state;

                public function __construct(?bool $state)
                {
                    $this->state = $state;
                }

                public function __invoke(Detector $detector): ?bool
                {
                    return $this->state;
                }
            }
        );

        Assert::equal($result, $detector->isDebugMode());
    }
}

(new DetectorTest())->run();
