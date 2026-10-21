<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2023 Redbit s.r.o., Jakub Bouček
 */

namespace PHPSTORM_META {
    expectedArguments(
        \Redbitcz\DebugMode\Detector::__construct(),
        0,
        \Redbitcz\DebugMode\Detector::ModeFull,
        \Redbitcz\DebugMode\Detector::ModeSimple,
        \Redbitcz\DebugMode\Detector::ModeEnabler,
        \Redbitcz\DebugMode\Detector::ModeCookie,
        \Redbitcz\DebugMode\Detector::ModeEnv,
        \Redbitcz\DebugMode\Detector::ModeIp
    );

    expectedArguments(
        \Redbitcz\DebugMode\Detector::detect(),
        0,
        \Redbitcz\DebugMode\Detector::ModeFull,
        \Redbitcz\DebugMode\Detector::ModeSimple,
        \Redbitcz\DebugMode\Detector::ModeEnabler,
        \Redbitcz\DebugMode\Detector::ModeCookie,
        \Redbitcz\DebugMode\Detector::ModeEnv,
        \Redbitcz\DebugMode\Detector::ModeIp
    );

    expectedArguments(
        \Redbitcz\DebugMode\Detector::detectProduction(),
        0,
        \Redbitcz\DebugMode\Detector::ModeFull,
        \Redbitcz\DebugMode\Detector::ModeSimple,
        \Redbitcz\DebugMode\Detector::ModeEnabler,
        \Redbitcz\DebugMode\Detector::ModeCookie,
        \Redbitcz\DebugMode\Detector::ModeEnv,
        \Redbitcz\DebugMode\Detector::ModeIp
    );

    expectedArguments(
        \Redbitcz\DebugMode\Plugin\SignedUrl::create(),
        1,
        'ES384',
        'ES256',
        'HS256',
        'HS384',
        'HS512',
        'RS256',
        'RS384',
        'RS512'
    );

    expectedArguments(
        \Redbitcz\DebugMode\Plugin\SignedUrl::signUrl(),
        2,
        \Redbitcz\DebugMode\Plugin\SignedUrl::ModeRequest,
        \Redbitcz\DebugMode\Plugin\SignedUrl::ModeEnabler,
        \Redbitcz\DebugMode\Plugin\SignedUrl::ModeDeactivateEnabler
    );

    expectedArguments(
        \Redbitcz\DebugMode\Plugin\SignedUrl::signUrl(),
        3,
        \Redbitcz\DebugMode\Plugin\SignedUrl::ValueDisable,
        \Redbitcz\DebugMode\Plugin\SignedUrl::ValueEnable
    );

    expectedArguments(
        \Redbitcz\DebugMode\Plugin\SignedUrl::getToken(),
        3,
        \Redbitcz\DebugMode\Plugin\SignedUrl::ModeRequest,
        \Redbitcz\DebugMode\Plugin\SignedUrl::ModeEnabler,
        \Redbitcz\DebugMode\Plugin\SignedUrl::ModeDeactivateEnabler
    );

    expectedArguments(
        \Redbitcz\DebugMode\Plugin\SignedUrl::getToken(),
        4,
        \Redbitcz\DebugMode\Plugin\SignedUrl::ValueDisable,
        \Redbitcz\DebugMode\Plugin\SignedUrl::ValueEnable
    );
}
