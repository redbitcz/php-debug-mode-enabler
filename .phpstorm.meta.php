<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2022 Redbit s.r.o., Jakub Bouček
 */

namespace PHPSTORM_META {

    expectedArguments(
        \Redbitcz\DebugMode\Detector::__construct(),
        0,
        \Redbitcz\DebugMode\Detector::MODE_FULL,
        \Redbitcz\DebugMode\Detector::MODE_SIMPLE,
        \Redbitcz\DebugMode\Detector::MODE_ENABLER,
        \Redbitcz\DebugMode\Detector::MODE_COOKIE,
        \Redbitcz\DebugMode\Detector::MODE_ENV,
        \Redbitcz\DebugMode\Detector::MODE_IP
    );

    expectedArguments(
        \Redbitcz\DebugMode\Detector::detect(),
        0,
        \Redbitcz\DebugMode\Detector::MODE_FULL,
        \Redbitcz\DebugMode\Detector::MODE_SIMPLE,
        \Redbitcz\DebugMode\Detector::MODE_ENABLER,
        \Redbitcz\DebugMode\Detector::MODE_COOKIE,
        \Redbitcz\DebugMode\Detector::MODE_ENV,
        \Redbitcz\DebugMode\Detector::MODE_IP
    );

    expectedArguments(
        \Redbitcz\DebugMode\Detector::detectProduction(),
        0,
        \Redbitcz\DebugMode\Detector::MODE_FULL,
        \Redbitcz\DebugMode\Detector::MODE_SIMPLE,
        \Redbitcz\DebugMode\Detector::MODE_ENABLER,
        \Redbitcz\DebugMode\Detector::MODE_COOKIE,
        \Redbitcz\DebugMode\Detector::MODE_ENV,
        \Redbitcz\DebugMode\Detector::MODE_IP
    );

    expectedArguments(
        \Redbitcz\DebugMode\Plugin\SignedUrl::__construct(),
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
        \Redbitcz\DebugMode\Plugin\SignedUrl::MODE_REQUEST,
        \Redbitcz\DebugMode\Plugin\SignedUrl::MODE_ENABLER,
        \Redbitcz\DebugMode\Plugin\SignedUrl::MODE_DEACTIVATE_ENABLER
    );

    expectedArguments(
        \Redbitcz\DebugMode\Plugin\SignedUrl::signUrl(),
        3,
        \Redbitcz\DebugMode\Plugin\SignedUrl::VALUE_DISABLE,
        \Redbitcz\DebugMode\Plugin\SignedUrl::VALUE_ENABLE
    );

    expectedArguments(
        \Redbitcz\DebugMode\Plugin\SignedUrl::getToken(),
        3,
        \Redbitcz\DebugMode\Plugin\SignedUrl::MODE_REQUEST,
        \Redbitcz\DebugMode\Plugin\SignedUrl::MODE_ENABLER,
        \Redbitcz\DebugMode\Plugin\SignedUrl::MODE_DEACTIVATE_ENABLER
    );

    expectedArguments(
        \Redbitcz\DebugMode\Plugin\SignedUrl::getToken(),
        4,
        \Redbitcz\DebugMode\Plugin\SignedUrl::VALUE_DISABLE,
        \Redbitcz\DebugMode\Plugin\SignedUrl::VALUE_ENABLE
    );
}
