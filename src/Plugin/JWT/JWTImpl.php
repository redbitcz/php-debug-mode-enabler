<?php

/**
 * The MIT License (MIT)
 * Copyright (c) 2022 Redbit s.r.o., Jakub Bouček
 */

declare(strict_types=1);

namespace Redbitcz\DebugMode\Plugin\JWT;

use stdClass;

interface JWTImpl
{
    public static function isAvailable(): bool;

    public function decode(string $jwt): stdClass;

    public function encode(array $payload): string;

    public function setTimestamp(?int $timestamp): void;
}
