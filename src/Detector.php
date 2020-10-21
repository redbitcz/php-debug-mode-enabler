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


    /** @var Enabler */
    private $enabler;

    public function __construct(string $tempDir)
    {
        $this->enabler = new Enabler($tempDir);
    }

    public function getEnabler(): Enabler
    {
        return $this->enabler;
    }

    public function isDebugMode(?bool $default = false): ?bool
    {
        return $this->isDebugModeByEnabler()
            ?? $this->isDebugModeByCookie()
            ?? $this->isDebugModeByEnv()
            ?? $this->isDebugModeByIp()
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

    public static function detect(string $tempDir, ?bool $default = false): ?bool
    {
        return (new self($tempDir))->isDebugMode($default);
    }
}
