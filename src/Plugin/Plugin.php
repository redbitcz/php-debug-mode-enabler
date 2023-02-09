<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2023 Redbit s.r.o., Jakub Bouček
 */

declare(strict_types=1);

namespace Redbitcz\DebugMode\Plugin;

use Redbitcz\DebugMode\Detector;

interface Plugin
{
    /**
     * Method invoked when Debug mode detection is called
     * Returned value:
     *   - `true` (force to turn-on debug mode)
     *   - `false` (force to turn-off debug mode)
     *   - `null` (unknown debug mode state, next detection method will be called)
     */
    public function __invoke(Detector $detector): ?bool;
}
