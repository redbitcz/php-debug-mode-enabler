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
        try {
            return JWT::decode($jwt, new Key($this->key, $this->algorithm));
        } catch (DomainException $e) {
            // Firebase reports malformed input as DomainException (a LogicException),
            // just like key configuration errors. Normalize only rejected JSON to a
            // RuntimeException so SignedUrl silently returns null and detection continues.
            // Revalidate only on failure to avoid relying on the library's error messages;
            // use Firebase's decoder because it also rejects whitespace-padded null.
            // Genuine configuration errors must still propagate.
            foreach (array_slice(explode('.', $jwt), 0, 2) as $segment) {
                try {
                    JWT::jsonDecode(JWT::urlsafeB64Decode($segment));
                } catch (DomainException) {
                    throw new UnexpectedValueException('Invalid JSON in JWT token', 0, $e);
                }
            }
            throw $e;
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
