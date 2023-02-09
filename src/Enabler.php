<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2023 Redbit s.r.o., Jakub Bouček
 */

declare(strict_types=1);

namespace Redbitcz\DebugMode;

use DateTimeInterface;
use Nette\IOException;
use Nette\Utils\DateTime as NetteDateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Nette\Utils\Random;

class Enabler
{
    private const STORAGE_FILE = '/debug/token.bin';
    private const DEBUG_COOKIE_NAME = 'app-debug-token';
    private const TOKEN_LENGTH = 30;
    private const ID_LENGTH = 15;
    private const DEFAULT_TTL = '1 hour';

    private string $tempDir;
    private ?bool $override = null;

    /** @var array<string, string|bool> */
    private array $cookieOptions = [
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ];

    public function __construct(string $tempDir)
    {
        $this->tempDir = $tempDir;
    }

    /** @return static */
    public function setSecure(bool $secure = true): self
    {
        $this->cookieOptions['secure'] = $secure;

        if ($secure) {
            $this->cookieOptions['samesite'] = 'Strict';
        } elseif (isset($this->cookieOptions['samesite'])) {
            unset ($this->cookieOptions['samesite']);
        }

        return $this;
    }

    /**
     * @return $this
     * @see setcookie()
     */
    public function setCookieOptions(
        ?string $path = null,
        ?string $domain = null,
        ?bool $secure = null,
        ?bool $httponly = null,
        ?string $samesite = null
    ): self {
        $this->cookieOptions = [
            'path' => $path ?? $this->cookieOptions['path'],
            'domain' => $domain ?? $this->cookieOptions['domain'],
            'secure' => $secure ?? $this->cookieOptions['secure'],
            'httponly' => $httponly ?? $this->cookieOptions['httponly'],
            'samesite' => $samesite ?? $this->cookieOptions['samesite'],
        ];
        return $this;
    }

    /**
     * @param string|int|DateTimeInterface|null $expires
     */
    public function activate(bool $isDebug, $expires = null): void
    {
        if ($tokenName = $this->getTokenName()) {
            $this->destroyToken($tokenName);
        }

        $tokenExpires = (int)NetteDateTime::from($expires ?? self::DEFAULT_TTL)->format('U');
        $cookieExpires = $expires === null ? 0 : $tokenExpires;
        $tokenName = $this->createToken($isDebug, $tokenExpires);
        setcookie(self::DEBUG_COOKIE_NAME, $tokenName, ['expires' => $cookieExpires] + $this->cookieOptions);

        $this->override = $isDebug;
    }


    public function deactivate(): void
    {
        if ($tokenName = $this->getTokenName()) {
            $this->destroyToken($tokenName);
        }

        setcookie(self::DEBUG_COOKIE_NAME, '', ['expires' => time()] + $this->cookieOptions);
        $this->override = null;
    }

    public function override(bool $isDebug): void
    {
        $this->override = $isDebug;
    }

    public function isDebug(): ?bool
    {
        return $this->isDebugByOverride()
            ?? $this->isDebugByToken();
    }

    private function isDebugByOverride(): ?bool
    {
        return $this->override;
    }

    private function isDebugByToken(): ?bool
    {
        $tokenName = $this->getTokenName();

        return isset($tokenName) ? $this->checkToken($tokenName) : null;
    }

    private function getTokenName(): ?string
    {
        return isset($_COOKIE[self::DEBUG_COOKIE_NAME]) ? (string)$_COOKIE[self::DEBUG_COOKIE_NAME] : null;
    }

    // TOKEN MANIPULATION ----------------------------------------------------------------------------------------------

    private function checkToken(string $name): ?bool
    {
        $list = $this->loadList();
        return $this->getListTokenValue($name, $list);
    }

    private function createToken(bool $value, int $expires): string
    {
        $list = $this->loadList();
        $name = $this->addListToken($value, $expires, $list);
        $this->saveList($list);

        return $name;
    }

    private function destroyToken(string $name): void
    {
        $list = $this->loadList();
        $this->dropListToken($name, $list);
        $this->saveList($list);
    }

    // TOKEN LIST ------------------------------------------------------------------------------------------------------

    /**
     * @return array[]
     */
    private function loadList(): array
    {
        return $this->readStorage();
    }

    /**
     * @param array<int, array> $list
     */
    private function addListToken(bool $value, int $expires, array &$list): string
    {
        /** @var string $name */
        [$name, $token] = $this->generateToken($value, $expires);
        $list[] = $token;
        return $name;
    }

    /**
     * @param array<int, array> $list
     */
    private function getListTokenValue(string $name, array $list): ?bool
    {
        $id = $this->getIdByName($name);
        foreach ($list as $token) {
            if ($token['id'] === $id && $this->isTokenValid($token, $name)) {
                return $token['value'];
            }
        }

        return null;
    }


    /**
     * @param array<int, array> $list
     */
    private function dropListToken(string $name, array &$list): void
    {
        $id = $this->getIdByName($name);
        $list = array_filter(
            $list,
            static function ($token) use ($id) {
                return $token['id'] !== $id;
            }
        );
    }

    /**
     * @param array<int, array> $list
     */
    private function saveList(array $list): void
    {
        // Filter invalid and expired tokens
        $list = array_filter(
            $list,
            function ($token) {
                return $this->isTokenValid($token);
            }
        );

        $this->writeStorage(array_values($list));
    }

    // TOKEN UTILS -----------------------------------------------------------------------------------------------------

    /**
     * @param array<string, string|bool|int> $token
     */
    private function isTokenValid(array $token, ?string $name = null): bool
    {
        $now = time();

        $result = isset($token['id'], $token['hash'], $token['expire'], $token['value'])
            && is_string($token['id'])
            && is_string($token['hash'])
            && is_int($token['expire'])
            && is_bool($token['value'])
            && $token['expire'] > $now;

        if (isset($name)) {
            $result = $result
                && $token['id'] === $this->getIdByName($name)
                && $token['hash'] === $this->getHashByName($name);
        }

        return $result;
    }

    /**
     * @return array<int, string|array>
     */
    private function generateToken(bool $value, int $expires): array
    {
        $name = $this->generateTokenName();
        $token = [
            'id' => $this->getIdByName($name),
            'hash' => $this->getHashByName($name),
            'expire' => $expires,
            'value' => $value
        ];

        return [$name, $token];
    }

    // STORAGE ---------------------------------------------------------------------------------------------------------

    /**
     * @return array<int, array>
     */
    private function readStorage(): array
    {
        $file = $this->getStorageFileName();

        try {
            return Json::decode(FileSystem::read($file), Json::FORCE_ARRAY);
        } catch (IOException $e) {
            // Yep, no error thrown, maybe file not exists
            return [];
        } catch (JsonException $e) {
            trigger_error(
                sprintf('%s: JSON Exception during read token file \'%s\': %s', __CLASS__, $file, $e->getMessage()),
                E_USER_WARNING
            );
            // It's low-level library, just re-create storage file at next write
            return [];
        }
    }

    /**
     * @param array<int, array> $list
     * @throws JsonException
     */
    private function writeStorage(array $list): void
    {
        $file = $this->getStorageFileName();

        if (count($list) > 0) {
            FileSystem::write($file, Json::encode($list));
        } else {
            FileSystem::delete($file);
        }
    }

    // UTILS -----------------------------------------------------------------------------------------------------------

    private function getIdByName(string $name): string
    {
        return substr($name, 0, self::ID_LENGTH);
    }

    private function getHashByName(string $name): string
    {
        return sha1($name);
    }

    private function getStorageFileName(): string
    {
        return $this->tempDir . self::STORAGE_FILE;
    }

    private function generateTokenName(): string
    {
        return Random::generate(self::TOKEN_LENGTH);
    }
}
