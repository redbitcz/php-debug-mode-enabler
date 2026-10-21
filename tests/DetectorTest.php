<?php

/**
 * The MIT License (MIT)
 * Copyright (c) 2023 Redbit s.r.o., Jakub Bouček
 *
 * @noinspection PhpUnused
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
    private const string TempDir = __DIR__ . '/temp/enabler';

    protected function setUp(): void
    {
        @mkdir(self::TempDir, 0777, true);
        Helpers::purge(self::TempDir);
    }

    public function testUndecidedDefaultsToProduction(): void
    {
        $detector = new Detector(0);
        Assert::null($detector->resolve());
        Assert::false($detector->isDebugMode());
        Assert::true($detector->isProductionMode());
        Assert::false(Detector::detect(0));
        Assert::true(Detector::detectProduction(0));
    }

    public function getDecisionProvider(): array
    {
        return [[null], [true], [false]];
    }

    /** @dataProvider getDecisionProvider */
    public function testResolvedDecision(?bool $decision): void
    {
        $detector = new Detector(0);
        $detector->appendPlugin(new class($decision) implements Plugin {
            private ?bool $decision;

            public function __construct(?bool $decision)
            {
                $this->decision = $decision;
            }

            public function __invoke(Detector $detector): ?bool
            {
                return $this->decision;
            }
        });

        Assert::same($decision, $detector->resolve());
        Assert::same($decision ?? false, $detector->isDebugMode());
        Assert::same(!($decision ?? false), $detector->isProductionMode());
    }

    /** @dataProvider getDecisionProvider */
    public function testStaticDecision(?bool $decision): void
    {
        $original = getenv(Detector::DebugEnvName);
        putenv(Detector::DebugEnvName . ($decision === null ? '' : '=' . (int)$decision));
        try {
            Assert::same($decision ?? false, Detector::detect(Detector::ModeEnv));
            Assert::same(!($decision ?? false), Detector::detectProduction(Detector::ModeEnv));
        } finally {
            putenv(Detector::DebugEnvName . ($original === false ? '' : '=' . $original));
        }
    }

    public function testRemovedDefaultArguments(): void
    {
        $detector = new Detector(0);
        foreach (['isDebugMode', 'isProductionMode'] as $method) {
            foreach ([null, false, true] as $default) {
                Assert::exception(static function () use ($detector, $method, $default) {
                    $detector->$method($default);
                }, \ArgumentCountError::class, '%a%no longer accepts a default%a%');
            }
            Assert::exception(static function () use ($detector, $method) {
                $detector->$method(default: null);
            }, \Error::class, 'Unknown named parameter $default');
        }
        foreach (['detect', 'detectProduction'] as $method) {
            foreach ([null, false, true] as $default) {
                Assert::exception(static function () use ($method, $default) {
                    Detector::$method(0, null, $default);
                }, \ArgumentCountError::class, '%a%no longer accepts a default%a%');
            }
            Assert::exception(static function () use ($method) {
                Detector::$method(default: null);
            }, \Error::class, 'Unknown named parameter $default');
            Assert::exception(static function () use ($method) {
                Detector::$method(Detector::ModeFull);
            }, InconsistentEnablerModeException::class);
            Assert::same($method === 'detectProduction', Detector::$method(Detector::ModeEnabler, self::TempDir));
        }
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
     */
    public function testEnv(?string $testValue, ?bool $expected): void
    {
        putenv(sprintf('%s%s%s', Detector::DebugEnvName, $testValue === null ? '' : '=', $testValue));

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
     */
    public function testCookie(string|int|null $testValue, ?bool $expected): void
    {
        if ($testValue !== null) {
            $_COOKIE[Detector::DebugCookieName] = $testValue;
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
     */
    public function testIp(string $testValue, ?bool $expected): void
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
    public function testSettedIp(array $setIp, string $testValue, ?bool $expected): void
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
    public function testAddedIp(array $setIp, string $testValue, ?bool $expected): void
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
     */
    public function testEnabler(bool $testValue): void
    {
        $detector = new Detector(Detector::ModeFull, new Enabler(self::TempDir));
        $detector->getEnabler()->override($testValue);
        Assert::equal($testValue, $detector->isDebugModeByEnabler());
    }

    public function testMissingEnablerMode(): void
    {
        Assert::exception(static function () {
            new Detector(Detector::ModeFull);
        }, InconsistentEnablerModeException::class);
    }

    public function testMissingEnabler(): void
    {
        Assert::exception(static function () {
            $detector = new Detector(Detector::ModeSimple);
            $detector->getEnabler();
        }, InconsistentEnablerModeException::class);
    }

    public function testMissingEnablerShortcut(): void
    {
        Assert::exception(static function () {
            Detector::detect(Detector::ModeFull);
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

        Assert::same($result, $detector->resolve());
        Assert::same($result, $detector->isDebugMode());
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

        Assert::same($result, $detector->resolve());
        Assert::same($result, $detector->isDebugMode());
    }
}

(new DetectorTest())->run();
