<?php

/**
 * The MIT License (MIT)
 * Copyright (c) 2022 Redbit s.r.o., Jakub Bouček
 */

declare(strict_types=1);

namespace Redbitcz\DebugMode\Plugin\JWT;

use Firebase\JWT\JWT;
use ReflectionMethod;
use stdClass;

class JWTFirebaseV5 implements JWTImpl
{
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

    public function decode(string $jwt, $key, string $alg): stdClass
    {
        return JWT::decode($jwt, $key, [$alg]);
    }

    public function encode(array $payload, $key, string $alg): string
    {
        return JWT::encode($payload, $key, $alg);
    }

    public function setTimestamp(?int $timestamp): void
    {
        JWT::$timestamp = $timestamp;
    }
}
