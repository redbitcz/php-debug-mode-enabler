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
    protected static $workDir;

    /**
     * @var string
     */
    protected static $tokenFile = '/debug/token.bin';

    /**
     * @var string
     */
    protected static $debugCookieName = 'debug-token';

    /**
     * @var string
     */
    protected static $debugEnvName = 'NETTE_DEBUG';

    /**
     * @var bool
     */
    protected static $cookieSecure = true;

    /**
     * @var int
     */
    protected static $tokenTtl = 3600;


    /**
     * @param bool|string|array $default
     * @param string|null $workDir
     * @return bool|string|array
     * @throws InvalidStateException
     * @throws \Nette\InvalidArgumentException
     */
    public static function isDebug($default = [], ?string $workDir = null)
    {
        return self::isDebugByEnv(false) || self::isDebugByToken(false, $workDir) || $default;
    }


    /**
     * @param bool|string|array $default
     * @return bool|string|array
     */
    public static function isDebugByEnv($default = [])
    {
        return (int)getenv(self::$debugEnvName) === 1 || $default;
    }


    /**
     * @param bool|string|array $default
     * @param string|null $workDir
     * @return bool|string|array
     * @throws InvalidStateException
     * @throws \Nette\InvalidArgumentException
     */
    public static function isDebugByToken($default = [], ?string $workDir = null)
    {
        $isValidToken = isset($_COOKIE[self::$debugCookieName])
            && ($_COOKIE[self::$debugCookieName] === self::getToken(false, $workDir));

        return $isValidToken ? true : $default;
    }


    /**
     * @param bool $create
     * @param string|null $workDir
     * @return string|null
     * @throws InvalidStateException
     * @throws \Nette\InvalidArgumentException
     */
    protected static function getToken(bool $create, ?string $workDir = null): ?string
    {
        $tokenFile = self::getTokenFile($workDir);
        if (file_exists($tokenFile)) {
            return file_get_contents($tokenFile);
        }

        if ($create) {
            return self::createToken($tokenFile);
        }

        return null;
    }


    /**
     * @param string|null $workDir
     * @return string
     * @throws InvalidStateException
     */
    protected static function getTokenFile(?string $workDir = null): string
    {
        if ($workDir === null && self::$workDir === null) {
            throw new InvalidStateException('WorkDir is undefined, unable to work with token file');
        }
        return self::$workDir . self::$tokenFile;
    }


    /**
     * @param string $tokenFile
     * @return string
     * @throws \Nette\InvalidArgumentException
     * @throws InvalidStateException
     */
    protected static function createToken($tokenFile): string
    {
        $token = self::generateToken();
        $dirname = \dirname($tokenFile);
        if (!\file_exists($dirname) && !mkdir($dirname, 0777, true) && !is_dir($dirname)) {
            throw new InvalidStateException(sprintf('Working directory "%s" was not created', $dirname));
        }
        file_put_contents($tokenFile, $token);

        return $token;
    }


    /**
     * @return string
     * @throws \Nette\InvalidArgumentException
     */
    protected static function generateToken(): string
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
     * @param bool $cookieSecure
     */
    public static function setCookieSecure(bool $cookieSecure = true): void
    {
        self::$cookieSecure = $cookieSecure;
    }


    /**
     * @param int $tokenTtl
     */
    public static function setTokenTtl(int $tokenTtl): void
    {
        self::$tokenTtl = $tokenTtl;
    }


    /**
     * @throws InvalidStateException
     * @throws \Nette\InvalidArgumentException
     */
    public static function turnOn(): void
    {
        $token = self::getToken(true);
        setcookie(
            self::$debugCookieName,
            $token,
            time() + self::$tokenTtl,
            '/',
            '',
            self::$cookieSecure,
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
            0,
            '/',
            '',
            self::$cookieSecure,
            true
        );
    }
}
