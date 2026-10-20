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
    private const string StorageFile = '/debug/token.bin';
    private const string DebugCookieName = 'app-debug-token';
    private const int TokenLength = 30;
    private const int IdLength = 15;
    private const string DefaultTtl = '1 hour';

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

    public function setSecure(bool $secure = true): static
    {
        $this->cookieOptions['secure'] = $secure;

        if ($secure) {
            $this->cookieOptions['samesite'] = 'Strict';
        } elseif (isset($this->cookieOptions['samesite'])) {
            unset ($this->cookieOptions['samesite']);
        }

        return $this;
    }

    /** @see setcookie() */
    public function setCookieOptions(
        ?string $path = null,
        ?string $domain = null,
        ?bool $secure = null,
        ?bool $httponly = null,
        ?string $samesite = null
    ): static {
        $this->cookieOptions = [
            'path' => $path ?? $this->cookieOptions['path'],
            'domain' => $domain ?? $this->cookieOptions['domain'],
            'secure' => $secure ?? $this->cookieOptions['secure'],
            'httponly' => $httponly ?? $this->cookieOptions['httponly'],
        ] + $this->cookieOptions;
        if ($samesite !== null) {
            $this->cookieOptions['samesite'] = $samesite;
        }
        return $this;
    }

    public function activate(bool $isDebug, string|int|DateTimeInterface|null $expires = null): void
    {
        if ($tokenName = $this->getTokenName()) {
            $this->destroyToken($tokenName);
        }

        $tokenExpires = (int)NetteDateTime::from($expires ?? self::DefaultTtl)->format('U');
        $cookieExpires = $expires === null ? 0 : $tokenExpires;
        $tokenName = $this->createToken($isDebug, $tokenExpires);
        setcookie(self::DebugCookieName, $tokenName, ['expires' => $cookieExpires] + $this->cookieOptions);

        $this->override = $isDebug;
    }


    public function deactivate(): void
    {
        if ($tokenName = $this->getTokenName()) {
            $this->destroyToken($tokenName);
        }

        setcookie(self::DebugCookieName, '', ['expires' => time()] + $this->cookieOptions);
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
        return isset($_COOKIE[self::DebugCookieName]) ? (string)$_COOKIE[self::DebugCookieName] : null;
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
            static function (array $token) use ($id): bool {
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
            function (array $token): bool {
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
     * @return array<int, array>
     * @phpstan-return list{string, array}
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
            $list = Json::decode(FileSystem::read($file), Json::FORCE_ARRAY);
            // Validate individual tokens only when looking up their value.
            return is_array($list) ? $list : [];
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
        return substr($name, 0, self::IdLength);
    }

    private function getHashByName(string $name): string
    {
        return sha1($name);
    }

    private function getStorageFileName(): string
    {
        return $this->tempDir . self::StorageFile;
    }

    private function generateTokenName(): string
    {
        return Random::generate(self::TokenLength);
    }
}
