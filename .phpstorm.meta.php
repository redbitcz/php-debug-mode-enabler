<?php

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
        \Redbitcz\DebugMode\Detector::detectProductionMode(),
        0,
        \Redbitcz\DebugMode\Detector::MODE_FULL,
        \Redbitcz\DebugMode\Detector::MODE_SIMPLE,
        \Redbitcz\DebugMode\Detector::MODE_ENABLER,
        \Redbitcz\DebugMode\Detector::MODE_COOKIE,
        \Redbitcz\DebugMode\Detector::MODE_ENV,
        \Redbitcz\DebugMode\Detector::MODE_IP
    );
}
