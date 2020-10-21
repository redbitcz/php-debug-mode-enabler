<?php

namespace PHPSTORM_META {

    expectedArguments(
        \Redbitcz\DebugMode\Detector::__construct(),
        1,
        \Redbitcz\DebugMode\Detector::MODE_ALL,
        \Redbitcz\DebugMode\Detector::MODE_ENABLER,
        \Redbitcz\DebugMode\Detector::MODE_COOKIE,
        \Redbitcz\DebugMode\Detector::MODE_ENV,
        \Redbitcz\DebugMode\Detector::MODE_IP
    );

    expectedArguments(
        \Redbitcz\DebugMode\Detector::detect(),
        1,
        \Redbitcz\DebugMode\Detector::MODE_ALL,
        \Redbitcz\DebugMode\Detector::MODE_ENABLER,
        \Redbitcz\DebugMode\Detector::MODE_COOKIE,
        \Redbitcz\DebugMode\Detector::MODE_ENV,
        \Redbitcz\DebugMode\Detector::MODE_IP
    );
}
