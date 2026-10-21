<?php

/**
 * The MIT License (MIT)
 * Copyright (c) 2023 Redbit s.r.o., Jakub Bouček
 *
 * @noinspection PhpComposerExtensionStubsInspection OpenSSL only optional dependency
 */

declare(strict_types=1);

namespace Redbitcz\DebugMode\Plugin\JWT;

use Composer\InstalledVersions;
use DomainException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use stdClass;
use UnexpectedValueException;

class JWTFirebaseV7 implements JWTImpl
{
    protected string|OpenSSLAsymmetricKey|OpenSSLCertificate $key;
    protected string $algorithm;

    public function __construct(
        string|OpenSSLAsymmetricKey|OpenSSLCertificate $key,
        string $algorithm = 'HS256'
    ) {
        $this->key = $key;
        $this->algorithm = $algorithm;
    }

    public static function isAvailable(): bool
    {
        if (!class_exists(JWT::class) || !class_exists(InstalledVersions::class)
            || !InstalledVersions::isInstalled('firebase/php-jwt')) {
            return false;
        }

        $version = InstalledVersions::getVersion('firebase/php-jwt');
        return $version !== null
            && version_compare($version, '7.0.0', '>=')
            && explode('.', $version)[0] === '7';
    }

    public function decode(string $jwt): stdClass
    {
        $this->validateTokenStructure($jwt);
        return JWT::decode($jwt, new Key($this->key, $this->algorithm));
    }

    /**
     * The Firebase JWT library throws DomainException (a LogicException) for invalid
     * JSON and TypeError for some invalid header types. This pre-validation is needed
     * even though the library also validates tokens: it reports these input failures
     * as UnexpectedValueException so SignedUrl can silently return null and continue
     * detection instead of aborting bootstrap. Configuration errors still propagate.
     */
    private function validateTokenStructure(string $jwt): void
    {
        $segments = explode('.', $jwt, 4);
        if (count($segments) !== 3) {
            throw new UnexpectedValueException('Wrong number of JWT segments');
        }

        try {
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode($segments[0]));
            JWT::jsonDecode(JWT::urlsafeB64Decode($segments[1]));
        } catch (DomainException $e) {
            throw new UnexpectedValueException('Invalid JSON in JWT token', 0, $e);
        }

        if (!$header instanceof stdClass || !is_string($header->alg ?? null)) {
            throw new UnexpectedValueException('JWT header alg must be a string');
        }
        if (isset($header->kid) && !is_string($header->kid)) {
            throw new UnexpectedValueException('JWT header kid must be a string or null');
        }
    }

    /** @param array<string, mixed> $payload */
    public function encode(array $payload): string
    {
        return JWT::encode($payload, $this->key, $this->algorithm);
    }

    public function setTimestamp(?int $timestamp): void
    {
        JWT::$timestamp = $timestamp;
    }
}
