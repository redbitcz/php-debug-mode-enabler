<?php

/**
 * The MIT License (MIT)
 * Copyright (c) 2023 Redbit s.r.o., Jakub Bouček
 */

declare(strict_types=1);

namespace Redbitcz\DebugMode\Plugin\JWT;

use stdClass;

interface JWTImpl
{
    public static function isAvailable(): bool;

    public function decode(string $jwt): stdClass;

    /** @param array<string, mixed> $payload */
    public function encode(array $payload): string;

    public function setTimestamp(?int $timestamp): void;
}
