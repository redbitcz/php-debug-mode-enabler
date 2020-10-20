<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2020 Redbit s.r.o., Jakub Bouček
 */

declare(strict_types=1);

namespace Redbitcz\DebugMode;

use Nette\IOException;
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
    private const TTL = 3600;
    // TODO: Make configurable TLS requirements
    private const REQUIRE_HTTPS = false;

    /** @var string */
    private $tempDir;

    /** @var bool|null */
    private $override;

    public function __construct(string $tempDir)
    {
        $this->tempDir = $tempDir;
    }

    public function activate(bool $isDebug): void
    {
        if ($tokenName = $this->getTokenName()) {
            $this->destroyToken($tokenName);
        }

        $tokenName = $this->createToken($isDebug);
        setcookie(
            self::DEBUG_COOKIE_NAME,
            $tokenName,
            [
                'expires' => time() + self::TTL,
                'path' => '/',
                'domain' => '',
                'secure' => self::REQUIRE_HTTPS,
                'httponly' => true,
                'samesite' => 'Strict',
            ]
        );

        $this->override = $isDebug;
    }


    public function deactivate(): void
    {
        if ($tokenName = $this->getTokenName()) {
            $this->destroyToken($tokenName);
        }

        setcookie(
            self::DEBUG_COOKIE_NAME,
            '',
            [
                'expires' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => self::REQUIRE_HTTPS,
                'httponly' => true,
                'samesite' => 'Strict',
            ]
        );

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

    private function createToken(bool $value): string
    {
        $list = $this->loadList();
        $name = $this->addListToken($value, $list);
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

    private function loadList(): array
    {
        return $this->readStorage();
    }

    private function addListToken(bool $value, array &$list): string
    {
        [$name, $token] = $this->generateToken($value);
        $list[] = $token;
        return $name;
    }

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

    private function saveList(array $list): void
    {
        // Filter invalid and expired tokens
        $list = array_filter(
            $list,
            function ($token) {
                return $this->isTokenValid($token);
            }
        );

        $this->writeStorage($list);
    }

    // TOKEN UTILS -----------------------------------------------------------------------------------------------------

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

    private function generateToken(bool $value): array
    {
        $name = $this->generateTokenName();
        $token = [
            'id' => $this->getIdByName($name),
            'hash' => $this->getHashByName($name),
            'expire' => time() + self::TTL,
            'value' => $value
        ];

        return [$name, $token];
    }

    // STORAGE ---------------------------------------------------------------------------------------------------------

    private function readStorage(): array
    {
        $file = $this->getStorageFileName();

        try {
            return Json::decode(FileSystem::read($file), Json::FORCE_ARRAY);
        } catch (JsonException | IOException $e) {
            // Yep, no error thrown, it's low-level library, just re-create storage file at next write
            return [];
        }
    }

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