<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2020 Redbit s.r.o., Jakub Bouček
 */

declare(strict_types=1);

namespace Redbitcz\DebugMode;

class Detector
{
    private const DEBUG_ENV_NAME = 'APP_DEBUG';
    private const DEBUG_COOKIE_NAME = 'app-debug-mode';

    public const MODE_ENABLER = 0b0001;
    public const MODE_COOKIE = 0b0010;
    public const MODE_ENV = 0b0100;
    public const MODE_IP = 0b1000;
    public const MODE_ALL = self::MODE_ENABLER | self::MODE_COOKIE | self::MODE_ENV | self::MODE_IP;

    /** @var Enabler */
    private $enabler;
    /** @var int */
    private $mode;

    public function __construct(string $tempDir, int $mode = self::MODE_ALL)
    {
        $this->enabler = new Enabler($tempDir);
        $this->mode = $mode;
    }

    public function getEnabler(): Enabler
    {
        return $this->enabler;
    }

    public function isDebugMode(?bool $default = false): ?bool
    {
        return ($this->mode & self::MODE_ENABLER ? $this->isDebugModeByEnabler() : null)
            ?? ($this->mode & self::MODE_COOKIE ? $this->isDebugModeByCookie() : null)
            ?? ($this->mode & self::MODE_ENV ? $this->isDebugModeByEnv() : null)
            ?? ($this->mode & self::MODE_IP ? $this->isDebugModeByIp() : null)
            ?? $default;
    }

    /**
     * Detect debug state by DobugModeEnabler helper
     * Returned value:
     *   - `false` (force to turn-off debug mode)
     *   - `true` (force to turn-on debug mode)
     *   - `null` (enabler is not activated)
     *
     * @return bool|null
     */
    public function isDebugModeByEnabler(): ?bool
    {
        return $this->enabler->isDebug();
    }

    /**
     * Detect disabling debug mode by Cookie: `app-debug-mode: 0`
     *
     * ENV value vs. returned value:
     *   - `0`: `false` (force to turn-off debug mode)
     *   - `undefined` or any other value (includes `1`): `null`
     *
     * Note: This cookie allows only turn-off Debug mode.
     * Using cookie to turn-on debug mode is unsecure!
     *
     * @return bool|null
     */
    public function isDebugModeByCookie(): ?bool
    {
        $cookieValue = $_COOKIE[self::DEBUG_COOKIE_NAME] ?? null;
        if (is_numeric($cookieValue) && (int)$cookieValue === 0) {
            return false;
        }

        return null;
    }

    /**
     * Detect debug state by ENV parameter
     * ENV value vs. returned value:
     *   - `0`: `false` (force to turn-off debug mode)
     *   - `1`: `true` (force to turn-on debug mode)
     *   - `undefined` or any other value: `null`
     *
     * @return bool|null
     */
    public function isDebugModeByEnv(): ?bool
    {
        $envValue = getenv(self::DEBUG_ENV_NAME);
        if (is_numeric($envValue) && in_array((int)$envValue, [0, 1], true)) {
            return (int)$envValue === 1;
        }

        return null;
    }

    /**
     * Detect debug state by locahost IP
     * Returned value:
     *   - is localhost: `true` (force to turn-on debug mode)
     *   - otherwise: `null`
     *
     * @return bool|null
     */
    public function isDebugModeByIp(): ?bool
    {
        $addr = $_SERVER['REMOTE_ADDR'] ?? php_uname('n');

        // Security check: Prevent false-positive match behind reverse proxy
        $result = isset($_SERVER['HTTP_X_FORWARDED_FOR']) === false
            && isset($_SERVER['HTTP_FORWARDED']) === false
            && (
                $addr === '::1'
                || preg_match('/^127\.0\.0\.\d+$/D', $addr)
            );

        return $result ?: null;
    }

    public static function detect(string $tempDir, int $mode = self::MODE_ALL, ?bool $default = false): ?bool
    {
        return (new self($tempDir, $mode))->isDebugMode($default);
    }
}
