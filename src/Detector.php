<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2023 Redbit s.r.o., Jakub Bouček
 */

declare(strict_types=1);

namespace Redbitcz\DebugMode;

use ArgumentCountError;
use Redbitcz\DebugMode\Plugin\Plugin;

class Detector
{
    /** Name of Environment variable used to detect Debug mode */
    public const string DebugEnvName = 'APP_DEBUG';

    /** Name of Cookie used to detect Debug mode */
    public const string DebugCookieName = 'app-debug-mode';

    /** Enables Debug mode detection by client IP */
    public const int ModeIp = 0b0001;

    /** Enables Debug mode detection by PHP process Environment variable */
    public const int ModeEnv = 0b0010;

    /** Enables Debug mode detection by request Cookie */
    public const int ModeCookie = 0b0100;

    /** Enables Debug mode detection by Enabler */
    public const int ModeEnabler = 0b1000;

    /** Simple mode without Enabler */
    public const int ModeSimple = self::ModeCookie | self::ModeEnv | self::ModeIp;

    /** Full mode with Enabler  */
    public const int ModeFull = self::ModeEnabler | self::ModeSimple;


    private Enabler $enabler;

    /** @var string[] */
    private array $ips = ['::1', '127.0.0.1'];

    /** @var array<int, callable(Detector): ?bool> */
    private array $detections;

    /**
     * @param int $mode Enables methods which is used to detect Debug mode
     * @param Enabler|null $enabler Enabler instance. Optional, but required when Enabler mode is enabled
     */
    public function __construct(int $mode = self::ModeSimple, ?Enabler $enabler = null)
    {
        if ($enabler === null && $mode & self::ModeEnabler) {
            throw new InconsistentEnablerModeException(
                'Enabler mode (and Full mode) requires the Enabler instance in constructor'
            );
        }

        $this->detections = array_filter(
            [
                $mode & self::ModeEnabler ? [$this, 'isDebugModeByEnabler'] : null,
                $mode & self::ModeCookie ? [$this, 'isDebugModeByCookie'] : null,
                $mode & self::ModeEnv ? [$this, 'isDebugModeByEnv'] : null,
                $mode & self::ModeIp ? [$this, 'isDebugModeByIp'] : null,
            ]
        );

        if ($enabler) {
            $this->enabler = $enabler;
        }
    }

    public function hasEnabler(): bool
    {
        return isset($this->enabler);
    }

    public function getEnabler(): Enabler
    {
        if (isset($this->enabler) === false) {
            throw new InconsistentEnablerModeException('Unable to get Enabler because Detector constructed without it');
        }

        return $this->enabler;
    }

    /** Resolve enabled rules, returning null when none decides. */
    public function resolve(): ?bool
    {
        foreach ($this->detections as $detection) {
            if (($result = $detection($this)) !== null) {
                return $result;
            }
        }
        return null;
    }

    /** Detect debug mode, defaulting to production when no rule decides. */
    public function isDebugMode(): bool
    {
        if (func_num_args() > 0) {
            throw new ArgumentCountError(__METHOD__ . ' no longer accepts a default; use resolve() instead');
        }

        return $this->resolve() ?? false;
    }

    /** Detect production mode, defaulting to production when no rule decides. */
    public function isProductionMode(): bool
    {
        if (func_num_args() > 0) {
            throw new ArgumentCountError(__METHOD__ . ' no longer accepts a default; use resolve() instead');
        }

        return !$this->isDebugMode();
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
        $cookieValue = $_COOKIE[self::DebugCookieName] ?? null;
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
        $envValue = getenv(self::DebugEnvName);
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
    public function setAllowedIp(string ...$ips): static
    {
        $this->ips = $ips;
        return $this;
    }

    /**
     * Add client IP address with allowed Debug mode
     */
    public function addAllowedIp(string ...$ips): static
    {
        $this->ips = array_merge($this->ips, $ips);
        return $this;
    }

    public function prependPlugin(Plugin $plugin): static
    {
        array_unshift($this->detections, $plugin);
        return $this;
    }

    public function appendPlugin(Plugin $plugin): static
    {
        $this->detections[] = $plugin;
        return $this;
    }

    /**
     * Detect debug mode without configuring an instance.
     *
     * @param int $mode Enabled detection methods
     * @param string|null $tempDir Required when Enabler mode is enabled
     */
    public static function detect(int $mode = self::ModeSimple, ?string $tempDir = null): bool
    {
        if (func_num_args() > 2) {
            throw new ArgumentCountError(__METHOD__ . ' no longer accepts a default; use resolve() on an instance instead');
        }

        return self::createDetector($mode, $tempDir)->isDebugMode();
    }

    /**
     * Detect production mode without configuring an instance.
     *
     * @param int $mode Enabled detection methods
     * @param string|null $tempDir Required when Enabler mode is enabled
     */
    public static function detectProduction(int $mode = self::ModeSimple, ?string $tempDir = null): bool
    {
        if (func_num_args() > 2) {
            throw new ArgumentCountError(__METHOD__ . ' no longer accepts a default; use resolve() on an instance instead');
        }

        return self::createDetector($mode, $tempDir)->isProductionMode();
    }

    private static function createDetector(int $mode, ?string $tempDir): self
    {
        if ($tempDir === null && $mode & self::ModeEnabler) {
            throw new InconsistentEnablerModeException('Enabler mode (and Full mode) requires \'tempDir\' argument');
        }

        $enabler = $tempDir === null ? null : new Enabler($tempDir);
        return new self($mode, $enabler);
    }
}
