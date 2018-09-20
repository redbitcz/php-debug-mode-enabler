<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2018 Jakub Bouček
 */

namespace JakubBoucek\DebugEnabler;

use Nette\Utils\Random;

class DebugEnabler
{
    /**
     * @var string
     */
    private static $workDir;

    /**
     * @var string
     */
    private static $tokenFile = '/debug/token.bin';

    /**
     * @var string
     */
    private static $debugCookieName = 'debug-token';

    /**
     * @var string
     */
    private static $debugEnvName = 'NETTE_DEBUG';


    /**
     * @param bool|string|array $default
     * @param string|null $workDir
     * @return bool|string|array
     * @throws \RuntimeException
     */
    public static function isDebug($default = [], ?string $workDir = null)
    {
        return self::isDebugByEnv() || self::isDebugByToken($workDir) || $default;
    }


    /**
     * @param bool|string|array $default
     * @return bool|string|array
     */
    public static function isDebugByEnv($default = false): bool
    {
        return (int)getenv(self::$debugEnvName) === 1 || $default;
    }


    /**
     * @param string|null $workDir
     * @return bool
     * @throws \RuntimeException
     */
    public static function isDebugByToken(?string $workDir = null): bool
    {
        return isset($_COOKIE[self::$debugCookieName])
            && ($_COOKIE[self::$debugCookieName] === self::getToken($workDir));
    }


    /**
     * @param string|null $workDir
     * @return string
     * @throws \RuntimeException
     */
    private static function getToken(?string $workDir = null): string
    {
        $tokenFile = self::getTokenFile($workDir);
        if (!file_exists($tokenFile)) {
            return self::createToken($tokenFile);
        }

        return file_get_contents($tokenFile);
    }


    /**
     * @param string|null $workDir
     * @return string
     * @throws InvalidStateException
     */
    private static function getTokenFile(?string $workDir = null): string
    {
        if ($workDir === null && self::$workDir === null) {
            throw new InvalidStateException('WorkDir is undefined, unable to work with token file');
        }
        return self::$workDir . self::$tokenFile;
    }


    /**
     * @param string $tokenFile
     * @return string
     * @throws \RuntimeException
     */
    private static function createToken($tokenFile): string
    {
        $token = self::generateToken();
        $dirname = \dirname($tokenFile);
        if (!\file_exists($dirname) && !mkdir($dirname, 0777, true) && !is_dir($dirname)) {
            throw new \RuntimeException(sprintf('Working directory "%s" was not created', $dirname));
        }
        file_put_contents($tokenFile, $token);
        return $token;
    }


    /**
     * @return string
     */
    private static function generateToken(): string
    {
        return Random::generate(30);
    }


    /**
     * @param string $workDir
     */
    public static function setWorkDir($workDir): void
    {
        self::$workDir = $workDir;
    }


    /**
     *
     * @throws \RuntimeException
     */
    public static function turnOn(): void
    {
        $token = self::getToken();
        setcookie(
            self::$debugCookieName,
            $token,
            time() + 3600,
            '/',
            '',
            true,
            true
        );
    }


    /**
     *
     */
    public static function turnOff(): void
    {
        setcookie(
            self::$debugCookieName,
            '',
            time() - 3600,
            '/',
            '',
            true,
            true
        );
    }
}
