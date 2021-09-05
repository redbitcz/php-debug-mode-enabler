<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2020 Redbit s.r.o., Jakub Bouček
 */

declare(strict_types=1);

namespace Redbitcz\DebugMode;

use InvalidArgumentException;
use LogicException;

class Detector
{
    /** Name of Environment variable used to detect Debug mode */
    public const DEBUG_ENV_NAME = 'APP_DEBUG';

    /** Name of Cookie used to detect Debug mode */
    public const DEBUG_COOKIE_NAME = 'app-debug-mode';

    /** Enables Debug mode detection by client IP */
    public const MODE_IP = 0b0001;

    /** Enables Debug mode detection by PHP process Environment variable */
    public const MODE_ENV = 0b0010;

    /** Enables Debug mode detection by request Cookie */
    public const MODE_COOKIE = 0b0100;

    /** Enables Debug mode detection by Enabler */
    public const MODE_ENABLER = 0b1000;

    /** Simple mode without Enabler */
    public const MODE_SIMPLE = self::MODE_COOKIE | self::MODE_ENV | self::MODE_IP;

    /** Full mode with Enabler  */
    public const MODE_FULL = self::MODE_ENABLER | self::MODE_SIMPLE;


    /** @var Enabler|null */
    private $enabler;
    /** @var int */
    private $mode;
    /** @var string[] */
    private $ips = ['::1', '127.0.0.1'];

    /**
     * @param int $mode Enables methods which is used to detect Debug mode
     * @param Enabler|null $enabler Enabler instance. Optional, but required when Enabler mode is enabled
     */
    public function __construct(int $mode = self::MODE_SIMPLE, ?Enabler $enabler = null)
    {
        if ($enabler === null && $mode & self::MODE_ENABLER) {
            throw new InvalidArgumentException('Enabler mode requires Enabler instance in constructor');
        }

        $this->enabler = $enabler;
        $this->mode = $mode;
    }

    public function getEnabler(): Enabler
    {
        if ($this->enabler === null) {
            throw new LogicException('Detector constructed without Enabler');
        }

        return $this->enabler;
    }

    /**
     * Detect Debug mode by all method enabled by Detector mode
     * Returned value:
     *   - `false` (force to turn-off debug mode)
     *   - `true` (force to turn-on debug mode)
     *   - `null` (unknown/automatic debug mode state)
     */
    public function isDebugMode(?bool $default = false): ?bool
    {
        return ($this->mode & self::MODE_ENABLER ? $this->isDebugModeByEnabler() : null)
            ?? ($this->mode & self::MODE_COOKIE ? $this->isDebugModeByCookie() : null)
            ?? ($this->mode & self::MODE_ENV ? $this->isDebugModeByEnv() : null)
            ?? ($this->mode & self::MODE_IP ? $this->isDebugModeByIp() : null)
            ?? $default;
    }

    /**
     * Detect Debug mode by `DebugMode\Enabler` helper
     * Returned value:
     *   - `false` (force to turn-off debug mode)
     *   - `true` (force to turn-on debug mode)
     *   - `null` (enabler is not activated)
     */
    public function isDebugModeByEnabler(): ?bool
    {
        return $this->getEnabler()->isDebug();
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
     * Detect Debug mode by ENV parameter
     * ENV value vs. returned value:
     *   - `0`: `false` (force to turn-off debug mode)
     *   - `1`: `true` (force to turn-on debug mode)
     *   - `undefined` or any other value: `null`
     */
    public function isDebugModeByEnv(): ?bool
    {
        $envValue = getenv(self::DEBUG_ENV_NAME);
        if ($envValue !== false && is_numeric($envValue) && in_array((int)$envValue, [0, 1], true)) {
            return (int)$envValue === 1;
        }

        return null;
    }

    /**
     * Detect debug state by match allowed IP addresses
     * Returned value:
     *   - is matched: `true` (force to turn-on debug mode)
     *   - not matched: `null`
     */
    public function isDebugModeByIp(): ?bool
    {
        $addr = $_SERVER['REMOTE_ADDR'] ?? php_uname('n');

        // Security check: Prevent false-positive match behind reverse proxy
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])
            || isset($_SERVER['HTTP_FORWARDED'])
            || isset($_SERVER['HTTP_X_REAL_IP'])) {
            return false;
        }

        return in_array($addr, $this->ips, true) ?: null;
    }

    /**
     * Set client IP address with allowed Debug mode
     */
    public function setAllowedIp(string ...$ips): self
    {
        $this->ips = $ips;
        return $this;
    }

    /**
     * Add client IP address with allowed Debug mode
     */
    public function addAllowedIp(string ...$ips): self
    {
        $this->ips = array_merge($this->ips, $ips);
        return $this;
    }

    /**
     * Shortcut to simple detect Debug mode by all method enabled by Detector mode (argument `$mode`)
     *
     * @param int $mode Enables methods which is used to detect Debug mode
     * @param string|null $tempDir Path to temp directory. Optional, but required when Enabler mode is enabled
     * @param bool|null $default Default value when no method matches
     */
    public static function detect(
        int $mode = self::MODE_SIMPLE,
        ?string $tempDir = null,
        ?bool $default = false
    ): ?bool {
        if ($tempDir === null && $mode & self::MODE_ENABLER) {
            throw new InvalidArgumentException('Enabler mode requires `tempDir` argument');
        }

        $enabler = $tempDir === null ? null : new Enabler($tempDir);
        return (new self($mode, $enabler))->isDebugMode($default);
    }

    /**
     * Shortcut to simple detect Production mode by all method enabled by Detector mode (argument `$mode`)
     *
     * @param int $mode Enables methods which is used to detect Debug mode
     * @param string|null $tempDir Path to temp directory. Optional, but required when Enabler mode is enabled
     * @param bool|null $default Default value when no method matches
     */
    public static function detectProductionMode(
        int $mode = self::MODE_SIMPLE,
        ?string $tempDir = null,
        ?bool $default = false
    ): ?bool {
        if (is_bool($default)) {
            $default = !$default;
        }

        $result = self::detect($mode, $tempDir, $default);

        if (is_bool($result)) {
            $result = !$result;
        }

        return $result;
    }
}
