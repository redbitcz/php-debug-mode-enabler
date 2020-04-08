<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2020 Redbit s.r.o., Jakub Bouček
 */

declare(strict_types=1);

namespace Redbitcz\DebugMode;

class DebugModeDetector
{
    private const DEBUG_ENV_NAME = 'PHP_APP_DEBUG_MODE';

    /** @var DebugModeEnabler */
    private $enabler;

    public function __construct(string $tempDir)
    {
        $this->enabler = new DebugModeEnabler($tempDir);
    }

    public function getEnabler(): DebugModeEnabler
    {
        return $this->enabler;
    }

    public function isDebugMode(): bool
    {
        return $this->isDebugModeByEnabler() ?? $this->isDebugModeByEnv() ?? $this->isDebugModeByIp() ?? false;
    }

    /**
     * Detect debug state by DobugModeEnabler helper
     * Returned value:
     *      - false (force to turn-off debug mode)
     *      - true (force to turn-on debug mode)
     *      - null (enabler is not activated)
     * @return bool|null
     */
    public function isDebugModeByEnabler(): ?bool
    {
        return $this->enabler->isDebug();
    }

    /**
     * Detect debug state by ENV parameter
     * ENV value vs. returned value:
     *      - 0: false (force to turn-off debug mode)
     *      - 1: true (force to turn-on debug mode)
     *      - undefined or any other value: null
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
     *      - is localhost: true (force to turn-on debug mode)
     *      - otherwise: null
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
}