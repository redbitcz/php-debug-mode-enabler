<?php

/**
 * The MIT License (MIT)
 * Copyright (c) 2023 Redbit s.r.o., Jakub Bouček
 *
 * @noinspection PhpUndefinedClassInspection Library support JWT 5.0
 */

declare(strict_types=1);

namespace Redbitcz\DebugMode\Plugin\JWT;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use ReflectionMethod;
use stdClass;

class JWTFirebaseV6 extends JWTFirebaseV5
{
    public static function isAvailable(): bool
    {
        if (class_exists(JWT::class) === false) {
            return false;
        }

        $params = (new ReflectionMethod(JWT::class, 'decode'))->getParameters();

        // JWT v6 has always second parameter named `$keyOrKeyArray`
        if ($params[1]->getName() !== 'keyOrKeyArray') {
            return false;
        }

        // JWT v5.5.0 already second parameter named `$keyOrKeyArray`, detect by third param (future compatibility)
        return isset($params[2]) === false || $params[2]->getName() !== 'allowed_algs';
    }

    public function decode(string $jwt): stdClass
    {
        return JWT::decode($jwt, new Key($this->key, $this->algorithm));
    }


}
