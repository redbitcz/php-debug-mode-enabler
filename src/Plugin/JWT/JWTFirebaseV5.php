<?php

/**
 * The MIT License (MIT)
 * Copyright (c) 2023 Redbit s.r.o., Jakub Bouček
 *
 * @noinspection PhpUndefinedClassInspection OpenSSL only optional dependency
 */

declare(strict_types=1);

namespace Redbitcz\DebugMode\Plugin\JWT;

use Firebase\JWT\JWT;
use ReflectionMethod;
use stdClass;

class JWTFirebaseV5 implements JWTImpl
{
    /** @var OpenSSLAsymmetricKey|OpenSSLCertificate|resource|string */
    protected $key;
    protected string $algorithm;

    /**
     * @param OpenSSLAsymmetricKey|OpenSSLCertificate|resource|string $key
     * @param string $algorithm
     */
    public function __construct($key, string $algorithm = 'HS256')
    {
        $this->key = $key;
        $this->algorithm = $algorithm;
    }

    public static function isAvailable(): bool
    {
        if (class_exists(JWT::class) === false) {
            return false;
        }

        $params = (new ReflectionMethod(JWT::class, 'decode'))->getParameters();

        // JWT v5 has second parameter named `$key`
        if ($params[1]->getName() === 'key') {
            return true;
        }

        // JWT v5.5.0 already second parameter named `$keyOrKeyArray`, detect by third param (future compatibility)
        return $params[1]->getName() === 'keyOrKeyArray'
            && isset($params[2])
            && $params[2]->getName() === 'allowed_algs';
    }

    public function decode(string $jwt): stdClass
    {
        return JWT::decode($jwt, $this->key, [$this->algorithm]);
    }

    public function encode(array $payload): string
    {
        return JWT::encode($payload, $this->key, $this->algorithm);
    }

    public function setTimestamp(?int $timestamp): void
    {
        JWT::$timestamp = $timestamp;
    }
}
